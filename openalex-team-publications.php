<?php
/**
 * Plugin Name: OpenAlex Team Publications
 * Description: Integra Team (tlp-team) con OpenAlex — usa edit.php estándar y guarda en teachPress
 * Version: 3.0
 */

if (!defined('ABSPATH')) exit;

class OpenAlexTeamPlugin {

    public function __construct() {
        add_filter('manage_team_posts_columns',        [$this, 'add_columns']);
        add_action('manage_team_posts_custom_column',  [$this, 'render_column'], 10, 2);
        add_filter('manage_edit-team_sortable_columns',[$this, 'sortable_columns']);
        add_action('pre_get_posts',                    [$this, 'sort_by_openalex_id']);
        add_action('restrict_manage_posts',            [$this, 'taxonomy_filter_ui']);
        add_filter('parse_query',                      [$this, 'taxonomy_filter_query']);
        add_action('quick_edit_custom_box',            [$this, 'quick_edit_field'], 10, 2);
        add_action('save_post_team',                   [$this, 'save_openalex_id']);
        add_filter('post_row_actions',                 [$this, 'row_actions'], 10, 2);
        add_action('admin_enqueue_scripts',            [$this, 'enqueue_scripts']);
        add_action('admin_menu',                       [$this, 'menu']);
        add_action('admin_post_openalex_sync',         [$this, 'handle_sync']);
    }

    // =========================================================================
    // COLUMNAS edit.php
    // =========================================================================

    public function add_columns(array $columns): array {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['openalex_id']       = 'OpenAlex ID';
                $new['openalex_last_sync'] = 'Última sync';
            }
        }
        return $new;
    }

    public function render_column(string $column, int $post_id): void {
        if ($column === 'openalex_id') {
            $id = get_post_meta($post_id, 'openalex_id', true);
            echo $id
                ? '<code>' . esc_html($id) . '</code><span class="hidden openalex-id-raw">' . esc_attr($id) . '</span>'
                : '<span aria-hidden="true">—</span><span class="hidden openalex-id-raw"></span>';
        }
        if ($column === 'openalex_last_sync') {
            $date = get_post_meta($post_id, 'openalex_last_sync', true);
            echo $date
                ? '<span title="' . esc_attr($date) . '">' . esc_html(date_i18n(get_option('date_format') . ' H:i', strtotime($date))) . '</span>'
                : '—';
        }
    }

    public function sortable_columns(array $columns): array {
        $columns['openalex_id'] = 'openalex_id';
        return $columns;
    }

    public function sort_by_openalex_id(\WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) return;
        if ($query->get('post_type') !== 'team') return;
        if ($query->get('orderby') === 'openalex_id') {
            $query->set('meta_key', 'openalex_id');
            $query->set('orderby', 'meta_value');
        }
    }

    // =========================================================================
    // FILTRO TAXONOMÍA
    // =========================================================================

    public function taxonomy_filter_ui(string $post_type): void {
        if ($post_type !== 'team') return;
        $selected = isset($_GET['team_designation']) ? sanitize_text_field($_GET['team_designation']) : '';
        $terms    = get_terms(['taxonomy' => 'team_designation', 'hide_empty' => false]);
        if (empty($terms) || is_wp_error($terms)) return;
        echo '<select name="team_designation">';
        echo '<option value="">Todos los equipos</option>';
        foreach ($terms as $t) {
            printf('<option value="%s"%s>%s</option>',
                esc_attr($t->slug),
                selected($selected, $t->slug, false),
                esc_html($t->name)
            );
        }
        echo '</select>';
    }

    public function taxonomy_filter_query(\WP_Query $query): void {
        global $pagenow;
        if ($pagenow !== 'edit.php') return;
        if (empty($_GET['team_designation'])) return;
        if (($query->query_vars['post_type'] ?? '') !== 'team') return;
        $query->set('tax_query', [[
            'taxonomy' => 'team_designation',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($_GET['team_designation']),
        ]]);
    }

    // =========================================================================
    // QUICK EDIT
    // =========================================================================

    public function quick_edit_field(string $column, string $post_type): void {
        if ($post_type !== 'team' || $column !== 'openalex_id') return;
        wp_nonce_field('openalex_quick_edit_nonce', 'openalex_quick_edit_nonce_field');
        ?>
        <fieldset class="inline-edit-col-left">
            <div class="inline-edit-col">
                <label>
                    <span class="title">OpenAlex ID</span>
                    <span class="input-text-wrap">
                        <input type="text" name="openalex_id" class="ptitle" value="" placeholder="Ej: A123456789">
                    </span>
                </label>
            </div>
        </fieldset>
        <?php
    }

    public function save_openalex_id(int $post_id): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (!isset($_POST['openalex_id'])) return;
        if (
            !isset($_POST['openalex_quick_edit_nonce_field']) ||
            !wp_verify_nonce($_POST['openalex_quick_edit_nonce_field'], 'openalex_quick_edit_nonce')
        ) return;
        update_post_meta($post_id, 'openalex_id', sanitize_text_field($_POST['openalex_id']));
    }

    // =========================================================================
    // ROW ACTIONS
    // =========================================================================

    public function row_actions(array $actions, \WP_Post $post): array {
        if ($post->post_type !== 'team') return $actions;
        $id = get_post_meta($post->ID, 'openalex_id', true);
        if (!$id) return $actions;
        $url = wp_nonce_url(
            admin_url('admin.php?page=openalex-publications&post_id=' . $post->ID),
            'openalex_view_' . $post->ID
        );
        $actions['openalex_view'] = '<a href="' . esc_url($url) . '">Ver publicaciones</a>';
        return $actions;
    }

    // =========================================================================
    // SCRIPTS
    // =========================================================================

    public function enqueue_scripts(string $hook): void {
        global $post_type;
        if ($hook !== 'edit.php' || $post_type !== 'team') return;
        wp_register_script('openalex-quick-edit-js', false, ['inline-edit-post'], '3.0', true);
        wp_enqueue_script('openalex-quick-edit-js');
        wp_add_inline_script('openalex-quick-edit-js', '
(function ($) {
    var $wpInlineEdit = inlineEditPost.edit;
    inlineEditPost.edit = function (id) {
        $wpInlineEdit.apply(this, arguments);
        var postId = (typeof id === "object") ? this.getId(id) : id;
        var currentId = $("#post-" + postId).find(".column-openalex_id .openalex-id-raw").text().trim();
        $("input[name=\"openalex_id\"]", "#edit-" + postId).val(currentId);
    };
}(jQuery));
        ');
    }

    // =========================================================================
    // MENÚ Y PÁGINA DE PUBLICACIONES
    // =========================================================================

    public function menu(): void {
        add_submenu_page(
            'edit.php?post_type=team',
            'Publicaciones OpenAlex',
            'Publicaciones OpenAlex',
            'manage_options',
            'openalex-publications',
            [$this, 'publications_page']
        );
    }

    public function publications_page(): void {
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

        echo '<div class="wrap"><h1>Publicaciones OpenAlex</h1>';

        // ── Mostrar resultado de sincronización si existe ──
        $transient_key = 'openalex_sync_result_' . get_current_user_id();
        $result = get_transient($transient_key);
        if ($result) {
            delete_transient($transient_key);
            $this->render_sync_notice($result);
        }

        if ($post_id) {
            // ── Vista de detalle: publicaciones de un miembro ──
            $this->render_member_detail($post_id);
        } else {
            // ── Vista de lista: todos los miembros con OpenAlex ID ──
            $this->render_members_list();
        }

        echo '</div>';
    }

    // ── Lista de todos los miembros con openalex_id ──────────────────────────
    private function render_members_list(): void {
        $members = get_posts([
            'post_type'   => 'team',
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
            'meta_query'  => [[
                'key'     => 'openalex_id',
                'value'   => '',
                'compare' => '!=',
            ]],
        ]);

        if (empty($members)) {
            echo '<div class="notice notice-info inline"><p>No hay miembros con OpenAlex ID asignado. '
               . 'Asignalos desde la <a href="' . esc_url(admin_url('edit.php?post_type=team')) . '">lista de Team</a>.</p></div>';
            return;
        }
        ?>
        <p>Miembros con OpenAlex ID: <strong><?php echo count($members); ?></strong>.
           Usá el botón <em>Sincronizar</em> para importar publicaciones a teachPress.</p>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Equipos</th>
                    <th>OpenAlex ID</th>
                    <th>Última sincronización</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($members as $m):
                $openalex_id = get_post_meta($m->ID, 'openalex_id', true);
                $last_sync   = get_post_meta($m->ID, 'openalex_last_sync', true);
                $terms       = get_the_terms($m->ID, 'team_designation');
                $team_names  = (!empty($terms) && !is_wp_error($terms))
                    ? implode(', ', wp_list_pluck($terms, 'name'))
                    : '—';
            ?>
            <tr>
                <td><strong><?php echo esc_html($m->post_title); ?></strong></td>
                <td><?php echo esc_html($team_names); ?></td>
                <td><code><?php echo esc_html($openalex_id); ?></code></td>
                <td>
                    <?php echo $last_sync
                        ? esc_html(date_i18n(get_option('date_format') . ' H:i', strtotime($last_sync)))
                        : '<em style="color:#8c8f94;">Nunca</em>';
                    ?>
                </td>
                <td>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                        <input type="hidden" name="action"  value="openalex_sync">
                        <input type="hidden" name="post_id" value="<?php echo $m->ID; ?>">
                        <?php wp_nonce_field('openalex_sync_' . $m->ID, 'openalex_sync_nonce'); ?>
                        <button type="submit" class="button button-primary">
                            <?php echo $last_sync ? '↻ Re-sincronizar' : '⬇ Sincronizar'; ?>
                        </button>
                    </form>
                    <?php
                    $view_url = admin_url('admin.php?page=openalex-publications&post_id=' . $m->ID);
                    echo ' <a class="button" href="' . esc_url($view_url) . '">Ver publicaciones</a>';
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // ── Vista de detalle de un miembro ───────────────────────────────────────
    private function render_member_detail(int $post_id): void {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'team') {
            echo '<div class="notice notice-error inline"><p>Miembro no encontrado.</p></div>';
            return;
        }

        $openalex_id = get_post_meta($post_id, 'openalex_id', true);
        $last_sync   = get_post_meta($post_id, 'openalex_last_sync', true);

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=openalex-publications')) . '">← Volver a la lista</a></p>';
        echo '<h2>' . esc_html($post->post_title) . '</h2>';

        if (!$openalex_id) {
            echo '<div class="notice notice-warning inline"><p>Este miembro no tiene OpenAlex ID asignado.</p></div>';
            return;
        }

        echo '<p><strong>OpenAlex ID:</strong> <code>' . esc_html($openalex_id) . '</code>';
        if ($last_sync) {
            echo ' &nbsp;|&nbsp; <strong>Última sync:</strong> '
               . esc_html(date_i18n(get_option('date_format') . ' H:i', strtotime($last_sync)));
        }
        echo '</p>';

        // Botón de sincronización en la vista de detalle
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:16px;">
            <input type="hidden" name="action"   value="openalex_sync">
            <input type="hidden" name="post_id"  value="<?php echo $post_id; ?>">
            <input type="hidden" name="redirect" value="detail">
            <?php wp_nonce_field('openalex_sync_' . $post_id, 'openalex_sync_nonce'); ?>
            <button type="submit" class="button button-primary">
                <?php echo $last_sync ? '↻ Re-sincronizar publicaciones' : '⬇ Sincronizar publicaciones'; ?>
            </button>
        </form>
        <?php

        // Mostrar publicaciones ya importadas en teachPress para este autor
        if ($this->teachpress_active()) {
            global $wpdb;
            $meta_table = $wpdb->prefix . 'teachpress_pub_meta';
            $pub_table  = $wpdb->prefix . 'teachpress_pub';

            $pubs = $wpdb->get_results($wpdb->prepare(
                "SELECT p.pub_id, p.title, p.type, DATE_FORMAT(p.date,'%%Y') AS year, p.doi
                 FROM {$pub_table} p
                 INNER JOIN {$meta_table} m ON m.pub_id = p.pub_id
                 WHERE m.meta_key = 'openalex_member_id' AND m.meta_value = %s
                 ORDER BY p.date DESC",
                $post_id
            ));

            if (!empty($pubs)) {
                echo '<p>Publicaciones importadas en teachPress: <strong>' . count($pubs) . '</strong></p>';
                echo '<table class="widefat striped"><thead><tr>'
                   . '<th>Título</th><th>Tipo</th><th>Año</th><th>DOI</th>'
                   . '</tr></thead><tbody>';
                foreach ($pubs as $pub) {
                    echo '<tr>'
                       . '<td>' . esc_html($pub->title) . '</td>'
                       . '<td><code>' . esc_html($pub->type) . '</code></td>'
                       . '<td>' . esc_html($pub->year) . '</td>'
                       . '<td>' . ($pub->doi ? '<a href="https://doi.org/' . esc_attr($pub->doi) . '" target="_blank">' . esc_html($pub->doi) . '</a>' : '—') . '</td>'
                       . '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p><em>No hay publicaciones importadas aún. Usá el botón de sincronización.</em></p>';
            }
        }
    }

    // ── Render del aviso de resultado ────────────────────────────────────────
    private function render_sync_notice(array $result): void {
        $type = empty($result['errors']) ? 'success' : 'warning';
        echo '<div class="notice notice-' . $type . ' is-dismissible"><p>';
        echo '<strong>' . esc_html($result['member_name']) . '</strong> — ';
        echo 'Publicaciones encontradas en OpenAlex: <strong>' . intval($result['total_found']) . '</strong>. ';
        echo 'Nuevas importadas a teachPress: <strong>' . intval($result['added']) . '</strong>. ';
        echo 'Ya existían: <strong>' . intval($result['skipped']) . '</strong>.';
        if (!empty($result['errors'])) {
            echo '<br><span style="color:#8a1a0a;">⚠ Errores: ' . esc_html(implode('; ', $result['errors'])) . '</span>';
        }
        echo '</p></div>';
    }

    // =========================================================================
    // HANDLER DE SINCRONIZACIÓN
    // =========================================================================

    public function handle_sync(): void {
        $post_id = intval($_POST['post_id'] ?? 0);

        if (!$post_id || !wp_verify_nonce($_POST['openalex_sync_nonce'] ?? '', 'openalex_sync_' . $post_id)) {
            wp_die('Solicitud no válida.', 403);
        }
        if (!current_user_can('manage_options')) {
            wp_die('Sin permisos.', 403);
        }
        if (!$this->teachpress_active()) {
            wp_die('teachPress no está activo. Activalo antes de sincronizar.', 400);
        }

        $result = $this->fetch_and_save($post_id);

        // Guardar resultado en transient para mostrarlo después del redirect
        set_transient('openalex_sync_result_' . get_current_user_id(), $result, 60);

        // Redirigir: si venía del detalle vuelve ahí, sino a la lista
        $redirect_to = (($_POST['redirect'] ?? '') === 'detail')
            ? admin_url('admin.php?page=openalex-publications&post_id=' . $post_id)
            : admin_url('admin.php?page=openalex-publications');

        wp_redirect($redirect_to);
        exit;
    }

    // =========================================================================
    // FETCH + GUARDADO EN TEACHPRESS
    // =========================================================================

    private function fetch_and_save(int $post_id): array {
        $member_name = get_the_title($post_id);
        $author_id   = get_post_meta($post_id, 'openalex_id', true);
        $author_id   = basename(trim($author_id)); // normaliza "https://openalex.org/A123" → "A123"

        $result = [
            'member_name' => $member_name,
            'total_found' => 0,
            'added'       => 0,
            'skipped'     => 0,
            'errors'      => [],
        ];

        // ── Paginación OpenAlex (cursor-based, máx 200/página) ───────────────
        $works      = [];
        $cursor     = '*';
        $per_page   = 200;
        $max_pages  = 10; // límite de seguridad: 2000 publicaciones máx
        $page_count = 0;

        do {
            $url      = "https://api.openalex.org/works?filter=author.id:{$author_id}"
                      . "&per-page={$per_page}&cursor=" . urlencode($cursor)
                      . "&select=id,title,type,publication_year,doi,authorships,biblio,"
                      . "primary_location,abstract_inverted_index";
            $response = wp_remote_get($url, ['timeout' => 30]);

            if (is_wp_error($response)) {
                $result['errors'][] = 'Error de conexión: ' . $response->get_error_message();
                break;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (empty($body['results'])) break;

            $works      = array_merge($works, $body['results']);
            $cursor     = $body['meta']['next_cursor'] ?? null;
            $page_count++;

        } while ($cursor && $page_count < $max_pages);

        $result['total_found'] = count($works);

        if (empty($works)) {
            return $result;
        }

        // ── Importar cada work a teachPress ──────────────────────────────────
        foreach ($works as $work) {
            $save_result = $this->save_work_to_teachpress($work, $post_id);
            if ($save_result === 'added') {
                $result['added']++;
            } elseif ($save_result === 'skipped') {
                $result['skipped']++;
            } else {
                $result['errors'][] = $save_result; // mensaje de error
            }
        }

        // ── Guardar fecha de última sincronización ────────────────────────────
        update_post_meta($post_id, 'openalex_last_sync', current_time('mysql'));

        return $result;
    }

    /**
     * Guarda un work de OpenAlex en teachPress.
     * Retorna 'added', 'skipped', o string de error.
     */
    private function save_work_to_teachpress(array $work, int $member_post_id): string {
        global $wpdb;

        $pub_meta_table = $wpdb->prefix . 'teachpress_pub_meta';
        $pub_table      = $wpdb->prefix . 'teachpress_pub';

        $openalex_work_id = basename($work['id'] ?? '');
        if (!$openalex_work_id) return 'Work sin ID, omitido.';

        // ── Deduplicación 1: por openalex_work_id en pub_meta ────────────────
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT pub_id FROM {$pub_meta_table} WHERE meta_key = 'openalex_work_id' AND meta_value = %s LIMIT 1",
            $openalex_work_id
        ));
        if ($existing_id) {
            // Asegurar relación con el miembro aunque la pub ya exista
            $this->ensure_member_relation($existing_id, $member_post_id);
            return 'skipped';
        }

        // ── Deduplicación 2: por DOI ─────────────────────────────────────────
        $doi_raw = $work['doi'] ?? '';
        $doi     = $doi_raw ? ltrim(str_replace('https://doi.org/', '', $doi_raw), '/') : '';
        if ($doi) {
            $existing_by_doi = $wpdb->get_var($wpdb->prepare(
                "SELECT pub_id FROM {$pub_table} WHERE doi = %s LIMIT 1",
                $doi
            ));
            if ($existing_by_doi) {
                // Agregar meta openalex_work_id para futuras dedups
                TP_Publications::add_pub_meta($existing_by_doi, 'openalex_work_id', $openalex_work_id);
                $this->ensure_member_relation($existing_by_doi, $member_post_id);
                return 'skipped';
            }
        }

        // ── Construir datos para teachPress ──────────────────────────────────
        $title   = $work['title'] ?? '[Sin título]';
        $type    = $this->map_pub_type($work['type'] ?? '');
        $year    = intval($work['publication_year'] ?? 0);
        $date    = $year > 0 ? "{$year}-01-01" : '0000-00-00';
        $volume  = $work['biblio']['volume']     ?? '';
        $issue   = $work['biblio']['issue']      ?? '';
        $page_s  = $work['biblio']['first_page'] ?? '';
        $page_e  = $work['biblio']['last_page']  ?? '';
        $pages   = ($page_s && $page_e) ? "{$page_s}--{$page_e}" : $page_s;

        $source      = $work['primary_location']['source'] ?? [];
        $journal     = $source['display_name'] ?? '';
        $publisher   = $source['host_organization_name'] ?? '';

        // Autores: "Last, First and Last, First and ..."
        $author_string = $this->build_author_string($work['authorships'] ?? []);

        // Abstract
        $abstract = '';
        if (!empty($work['abstract_inverted_index'])) {
            $abstract = $this->reconstruct_abstract($work['abstract_inverted_index']);
        }

        // URL
        $url = '';
        if (!empty($work['primary_location']['landing_page_url'])) {
            $url = $work['primary_location']['landing_page_url'];
        }

        // BibTeX key: primera palabra del autor + año + primera palabra título
        $bibtex_key = $this->generate_bibtex_key($author_string, $year, $title);

        $data = [
            'type'      => $type,
            'bibtex'    => $bibtex_key,
            'title'     => $title,
            'author'    => $author_string,
            'doi'       => $doi,
            'url'       => $url,
            'date'      => $date,
            'journal'   => $journal,
            'volume'    => $volume,
            'issue'     => $issue,
            'pages'     => $pages,
            'publisher' => $publisher,
            'abstract'  => $abstract,
            'status'    => 'published',
        ];

        // ── Insertar en teachPress ────────────────────────────────────────────
        $pub_id = TP_Publications::add_publication($data, '');

        if (!$pub_id) {
            return "Error al guardar '{$title}' en teachPress.";
        }

        // ── Metadatos adicionales ─────────────────────────────────────────────
        TP_Publications::add_pub_meta($pub_id, 'openalex_work_id',   $openalex_work_id);
        TP_Publications::add_pub_meta($pub_id, 'openalex_member_id', $member_post_id);

        // ── Relaciones de autores individuales (TEACHPRESS_REL_PUB_AUTH) ──────
        $this->save_author_relations($pub_id, $work['authorships'] ?? []);

        return 'added';
    }

    /**
     * Asegura que exista el meta openalex_member_id para una pub ya existente.
     */
    private function ensure_member_relation(int $pub_id, int $member_post_id): void {
        global $wpdb;
        $meta_table = $wpdb->prefix . 'teachpress_pub_meta';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_id FROM {$meta_table}
             WHERE pub_id = %d AND meta_key = 'openalex_member_id' AND meta_value = %s LIMIT 1",
            $pub_id, $member_post_id
        ));
        if (!$exists) {
            TP_Publications::add_pub_meta($pub_id, 'openalex_member_id', $member_post_id);
        }
    }

    /**
     * Guarda relaciones individuales autor↔publicación en TEACHPRESS_REL_PUB_AUTH.
     * teachPress ya crea las relaciones via add_publication() a partir del string
     * "author", pero aquí usamos los IDs de OpenAlex para mayor precisión.
     */
    private function save_author_relations(int $pub_id, array $authorships): void {
        global $wpdb;
        $authors_table = $wpdb->prefix . 'teachpress_authors';
        $rel_table     = $wpdb->prefix . 'teachpress_rel_pub_auth';

        // Limpiar relaciones creadas por add_publication() para rehacerlas con datos precisos
        $wpdb->delete($rel_table, ['pub_id' => $pub_id], ['%d']);

        foreach ($authorships as $authorship) {
            $display_name = $authorship['author']['display_name'] ?? '';
            if (!$display_name) continue;

            $formatted = $this->format_author_name($display_name);
            $sort_name = $this->get_sort_name($display_name);

            // Buscar autor por nombre exacto
            $author_id = $wpdb->get_var($wpdb->prepare(
                "SELECT author_id FROM {$authors_table} WHERE name = %s LIMIT 1",
                $formatted
            ));

            // Si no existe, crear
            if (!$author_id) {
                $author_id = TP_Authors::add_author($formatted, $sort_name);
            }

            if ($author_id) {
                TP_Authors::add_author_relation($pub_id, $author_id, 1, 0);
            }
        }
    }

    // =========================================================================
    // UTILIDADES
    // =========================================================================

    private function teachpress_active(): bool {
        return class_exists('TP_Publications') && class_exists('TP_Authors');
    }

    private function map_pub_type(string $type): string {
        return [
            'article'              => 'article',
            'journal-article'      => 'article',
            'book-chapter'         => 'inbook',
            'book'                 => 'book',
            'edited-book'          => 'book',
            'proceedings-article'  => 'inproceedings',
            'conference-paper'     => 'inproceedings',
            'review'               => 'article',
            'dissertation'         => 'phdthesis',
            'thesis'               => 'phdthesis',
            'preprint'             => 'unpublished',
            'report'               => 'techreport',
            'dataset'              => 'misc',
            'other'                => 'misc',
        ][$type] ?? 'misc';
    }

    private function build_author_string(array $authorships): string {
        $names = [];
        foreach ($authorships as $a) {
            $name = $a['author']['display_name'] ?? '';
            if ($name) $names[] = $this->format_author_name($name);
        }
        return implode(' and ', $names);
    }

    /** "John William Smith" → "Smith, John William" */
    private function format_author_name(string $display_name): string {
        $parts = preg_split('/\s+/', trim($display_name));
        if (count($parts) < 2) return $display_name;
        $last  = array_pop($parts);
        $first = implode(' ', $parts);
        return $last . ', ' . $first;
    }

    /** Apellido para campo sort_name */
    private function get_sort_name(string $display_name): string {
        $parts = preg_split('/\s+/', trim($display_name));
        return array_pop($parts) ?? $display_name;
    }

    private function reconstruct_abstract(array $inverted_index): string {
        $positions = [];
        foreach ($inverted_index as $word => $pos_list) {
            foreach ($pos_list as $pos) {
                $positions[$pos] = $word;
            }
        }
        ksort($positions);
        return implode(' ', $positions);
    }

    private function generate_bibtex_key(string $author_string, int $year, string $title): string {
        // "Smith, John and ..." → "Smith"
        $first_author = strtok($author_string, ',') ?: 'unknown';
        $first_author = preg_replace('/[^a-zA-Z]/', '', $first_author);

        // Primera palabra significativa del título
        $title_words = preg_split('/\s+/', $title);
        $first_word  = '';
        $stopwords   = ['a', 'an', 'the', 'of', 'in', 'on', 'at', 'to', 'and', 'or'];
        foreach ($title_words as $w) {
            $clean = preg_replace('/[^a-zA-Z]/', '', strtolower($w));
            if ($clean && !in_array($clean, $stopwords)) {
                $first_word = ucfirst($clean);
                break;
            }
        }

        return strtolower($first_author) . ($year ?: 'nd') . $first_word;
    }
}

new OpenAlexTeamPlugin();