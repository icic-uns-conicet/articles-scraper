<?php
/**
 * Plugin Name: OpenAlex Team Publications
 * Description: Integra Team (tlp-team) con OpenAlex y guarda publicaciones en teachPress
 * Version:     4.0
 * Author:      Carlos Lorenzetti
 * Text Domain: openalex-team
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Constantes del plugin
define( 'OPENALEX_TEAM_VERSION', '4.0' );
define( 'OPENALEX_TEAM_PATH',    plugin_dir_path( __FILE__ ) );
define( 'OPENALEX_TEAM_URL',     plugin_dir_url( __FILE__ ) );

// Autoload de clases del plugin
require_once OPENALEX_TEAM_PATH . 'includes/class-helpers.php';
require_once OPENALEX_TEAM_PATH . 'core/class-openalex-api.php';
require_once OPENALEX_TEAM_PATH . 'core/class-teachpress-import.php';
require_once OPENALEX_TEAM_PATH . 'admin/class-admin-columns.php';
require_once OPENALEX_TEAM_PATH . 'admin/class-admin-sync.php';
require_once OPENALEX_TEAM_PATH . 'admin/class-publications-page.php';
require_once OPENALEX_TEAM_PATH . 'admin/class-settings.php';
require_once OPENALEX_TEAM_PATH . 'frontend/class-single-team.php';


/**
 * Clase principal: registra todos los módulos.
 */
class OpenAlexTeamPlugin {

    public function __construct() {
		new OpenAlex_Settings();
        new OpenAlex_Admin_Columns();
        new OpenAlex_Admin_Sync();
        new OpenAlex_Publications_Page();
        new OpenAlex_Single_Team();
    }
}

new OpenAlexTeamPlugin();