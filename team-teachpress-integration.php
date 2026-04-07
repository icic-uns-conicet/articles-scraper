<?php
/**
 * Plugin Name: Team - TeachPress Integration
 * Description: Muestra publicaciones de TeachPress en las páginas de miembros de Team.
 * Version: 1.0
 * Author: Kcho
 */

add_action('the_content', 'add_teachpress_to_team_member',100);

function add_teachpress_to_team_member($content) {
    $post_type = get_post_type();
    if (($post_type === 'team') && 
        (function_exists('tp_list_shortcode')) && 
        (function_exists('get_field'))) {
            
        global $post;

        // Supone que el nombre del miembro es el mismo que el autor TeachPress
        $author_name = get_the_title($post->ID);

        // Obtén publicaciones de TeachPress
        $publications = tp_list_shortcode(array('author' => $author_name));
        //$post_array = implode(', ', $post);
        
        $google_scholar = get_field('google_scholar_id', get_the_ID());
        if( $google_scholar ) {
            $content .= $google_scholar ;
        } else {
            echo '';
        }
        

        
        /*
        if (!empty($publications)) {
            // $content .= '<h3>Publicaciones</h3><ul>';
            foreach ($publications as $publication) {
                // $content .= '<li>' . esc_html($publication['title']) . '</li>';
            }
            // $content .= '</ul>';
        } else {
            // $content .= '<p>No hay publicaciones disponibles.</p>';
        }
        */
    }
    return $content;
}

// Agregar el menú de administración
add_action('admin_menu', 'tt_integration_add_tools_submenu');
/*
function tt_integration_add_admin_menu() {
    add_menu_page(
        'Team & TeachPress Status', // Título de la página
        'TT Integration',          // Título del menú
        'manage_options',          // Capacidad requerida
        'tt-integration-status',   // Slug del menú
        'tt_integration_status_page', // Callback para mostrar el contenido
        'dashicons-admin-plugins', // Icono del menú
        80                         // Posición del menú
    );
}
*/
function tt_integration_add_tools_submenu() {
    add_submenu_page(
        'tools.php',                // Menú padre (Herramientas)
        'Team & TeachPress Status', // Título de la página
        'TT Integration',           // Título del submenú
        'manage_options',           // Capacidad requerida
        'tt-integration-status',    // Slug del submenú
        'tt_integration_status_page'// Callback para mostrar el contenido
    );
}

// Función para verificar si los plugins están activos
function tt_integration_status_page() {
    // Verificar si las funciones de los plugins existen
    
    $teachpress_active = (in_array('teachpress/teachpress.php', apply_filters('active_plugins', get_option('active_plugins'))))?true:false;
    $team_active = (in_array('tlp-team/tlp-team.php', apply_filters('active_plugins', get_option('active_plugins'))))?true:false;

    echo '<div class="wrap">';
    echo '<h1>Estado de la Integración</h1>';
    echo '<p>Verifica si los plugins necesarios están instalados y activos.</p>';

    echo '<h2>Estado de los Plugins</h2>';
    echo '<ul>';
    echo '<li>Team: ' . ($team_active ? '<span style="color:green;">Activo</span>' : '<span style="color:red;">Inactivo</span>') . '</li>';
    echo '<li>TeachPress: ' . ($teachpress_active ? '<span style="color:green;">Activo</span>' : '<span style="color:red;">Inactivo</span>') . '</li>';
    echo '</ul>';

    // Mensajes de acción
    if (!$team_active) {
        echo '<p><strong>Nota:</strong> Instala y activa el plugin <em>Team</em> para que la integración funcione correctamente.</p>';
    }
    if (!$teachpress_active) {
        echo '<p><strong>Nota:</strong> Instala y activa el plugin <em>TeachPress</em> para que la integración funcione correctamente.</p>';
    }

    echo '</div>';
}
