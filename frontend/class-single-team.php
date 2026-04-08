<?php
/**
 * Inyección del listado de publicaciones en la página single
 * del CPT 'team' (generada por el plugin tlp-team).
 *
 * @package OpenAlexTeam
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OpenAlex_Single_Team {

    public function __construct() {
        add_filter( 'template_include',   [ $this, 'intercept_template' ], 99 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
    }

    /**
     * Cuando se detecta single-team.php, registra el hook de inyección.
     */
    public function intercept_template( string $template ): string {
        if ( is_singular( 'team' ) && strpos( basename( $template ), 'single-team' ) !== false ) {
            add_action( 'wp_footer', [ $this, 'inject_publications' ], 1 );
        }
        return $template;
    }

    /**
     * Genera el bloque HTML e inyecta vía JS al final del contenedor del miembro.
     */
    public function inject_publications(): void {
        if ( ! is_singular( 'team' ) ) return;
        if ( ! OpenAlex_Helpers::teachpress_active() ) return;

        global $post;
        $pubs = OpenAlex_Helpers::get_member_publications( $post->ID );
        if ( empty( $pubs ) ) return;

        $html = $this->render_publications_html( $pubs );
        ?>
        <script>
        (function () {
            var html = <?php echo wp_json_encode( $html ); ?>;
            var targets = [
                '.tlp-single-container',
                '.tlp-single-detail',
                'article.type-team',
                'main',
                '#content',
                '.site-content'
            ];
            function insert() {
                for ( var i = 0; i < targets.length; i++ ) {
                    var el = document.querySelector( targets[i] );
                    if ( el ) {
                        var div = document.createElement('div');
                        div.innerHTML = html;
                        el.appendChild(div);
                        return;
                    }
                }
                document.body.insertAdjacentHTML('beforeend', html);
            }
            document.readyState === 'loading'
                ? document.addEventListener('DOMContentLoaded', insert)
                : insert();
        })();
        </script>
        <?php
    }

    public function enqueue_styles(): void {
        if ( ! is_singular( 'team' ) ) return;
        wp_register_style( 'openalex-frontend', false );
        wp_enqueue_style( 'openalex-frontend' );
        wp_add_inline_style( 'openalex-frontend', $this->get_css() );
    }

    // ── HTML del bloque de publicaciones ─────────────────────────────────────

    private function render_publications_html( array $pubs ): string {
        $by_year = [];
        foreach ( $pubs as $pub ) {
            $by_year[ $pub->year ?: 'Sin año' ][] = $pub;
        }
        krsort( $by_year );

        ob_start(); ?>
        <div class="openalex-publications">
            <h3 class="openalex-publications__title">
                Publicaciones
                <span class="openalex-publications__count">(<?php echo count( $pubs ); ?>)</span>
            </h3>
            <?php foreach ( $by_year as $year => $year_pubs ): ?>
            <div class="openalex-publications__year-group">
                <h4 class="openalex-publications__year"><?php echo esc_html( $year ); ?></h4>
                <ul class="openalex-publications__list">
                    <?php foreach ( $year_pubs as $pub ): ?>
                    <li class="openalex-publications__item">
                        <span class="openalex-pub-type openalex-pub-type--<?php echo esc_attr( $pub->type ); ?>">
                            <?php echo esc_html( OpenAlex_Helpers::get_type_label( $pub->type ) ); ?>
                        </span>
                        <span class="openalex-pub-title">
                            <?php if ( $pub->doi ): ?>
                                <a href="https://doi.org/<?php echo esc_attr( $pub->doi ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $pub->title ); ?></a>
                            <?php elseif ( $pub->url ): ?>
                                <a href="<?php echo esc_url( $pub->url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $pub->title ); ?></a>
                            <?php else: ?>
                                <?php echo esc_html( $pub->title ); ?>
                            <?php endif; ?>
                        </span>
                        <?php if ( $pub->author ): ?>
                        <span class="openalex-pub-authors">
                            <?php echo esc_html( OpenAlex_Helpers::format_author_list( $pub->author ) ); ?>
                        </span>
                        <?php endif; ?>
                        <?php if ( $pub->journal ): ?>
                        <em class="openalex-pub-journal"><?php echo esc_html( $pub->journal ); ?></em>
                        <?php endif; ?>
                        <?php if ( $pub->doi ): ?>
                        <a class="openalex-pub-doi" href="https://doi.org/<?php echo esc_attr( $pub->doi ); ?>" target="_blank" rel="noopener noreferrer">
                            DOI: <?php echo esc_html( $pub->doi ); ?>
                        </a>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php return ob_get_clean();
    }

    private function get_css(): string {
        return '
.openalex-publications { margin-top:2.5em; padding-top:1.5em; border-top:2px solid #e0e0e0; font-size:.95em; line-height:1.55; }
.openalex-publications__title { font-size:1.4em; font-weight:700; margin-bottom:.75em; }
.openalex-publications__count { font-weight:400; font-size:.85em; color:#666; margin-left:.25em; }
.openalex-publications__year-group { margin-bottom:1.5em; }
.openalex-publications__year { font-size:1.05em; font-weight:600; color:#444; border-bottom:1px solid #e8e8e8; padding-bottom:.25em; margin-bottom:.6em; }
.openalex-publications__list { list-style:none; margin:0; padding:0; }
.openalex-publications__item { display:flex; flex-wrap:wrap; align-items:baseline; gap:.35em; padding:.55em 0; border-bottom:1px solid #f2f2f2; }
.openalex-publications__item:last-child { border-bottom:none; }
.openalex-pub-type { display:inline-block; font-size:.7em; font-weight:600; text-transform:uppercase; letter-spacing:.05em; padding:2px 7px; border-radius:3px; white-space:nowrap; background:#e8f0fe; color:#1a56c4; }
.openalex-pub-type--article { background:#e8f5e9; color:#2e7d32; }
.openalex-pub-type--inproceedings { background:#fff3e0; color:#e65100; }
.openalex-pub-type--book, .openalex-pub-type--inbook { background:#fce4ec; color:#880e4f; }
.openalex-pub-type--phdthesis { background:#ede7f6; color:#4527a0; }
.openalex-pub-type--misc, .openalex-pub-type--unpublished { background:#f5f5f5; color:#555; }
.openalex-pub-title { font-weight:600; flex:1 1 100%; }
.openalex-pub-title a { color:inherit; text-decoration:underline; text-underline-offset:2px; }
.openalex-pub-title a:hover { opacity:.75; }
.openalex-pub-authors { color:#555; font-size:.9em; flex:1 1 100%; }
.openalex-pub-journal { color:#666; font-size:.9em; }
.openalex-pub-doi { font-size:.8em; color:#888; text-decoration:none; }
.openalex-pub-doi:hover { text-decoration:underline; }
        ';
    }
}
