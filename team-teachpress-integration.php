<?php
/**
 * Plugin Name: OpenAlex Team Publications
 * Description: Integra Team (tlp-team) con OpenAlex y guarda publicaciones en teachPress
 * Version:     4.1
 * Author:      Carlos Lorenzetti
 * Text Domain: openalex-team
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'OPENALEX_TEAM_VERSION', '4.1' );
define( 'OPENALEX_TEAM_PATH',    plugin_dir_path( __FILE__ ) );
define( 'OPENALEX_TEAM_URL',     plugin_dir_url( __FILE__ ) );

/**
 * Action Scheduler
 */
if ( file_exists( OPENALEX_TEAM_PATH . 'vendor/action-scheduler/action-scheduler.php' ) ) {
    require_once OPENALEX_TEAM_PATH . 'vendor/action-scheduler/action-scheduler.php';
}

require_once OPENALEX_TEAM_PATH . 'includes/class-helpers.php';
require_once OPENALEX_TEAM_PATH . 'core/class-openalex-api.php';
require_once OPENALEX_TEAM_PATH . 'core/class-teachpress-import.php';
require_once OPENALEX_TEAM_PATH . 'core/class-job-queue.php';
require_once OPENALEX_TEAM_PATH . 'admin/class-settings.php';
require_once OPENALEX_TEAM_PATH . 'admin/class-admin-columns.php';
require_once OPENALEX_TEAM_PATH . 'admin/class-admin-sync.php';
require_once OPENALEX_TEAM_PATH . 'admin/class-publications-page.php';
require_once OPENALEX_TEAM_PATH . 'frontend/class-single-team.php';

class OpenAlexTeamPlugin {

    public function __construct() {
        new OpenAlex_Settings();
        new OpenAlex_Job_Queue();
        new OpenAlex_Admin_Columns();
        new OpenAlex_Admin_Sync();
        new OpenAlex_Publications_Page();
        new OpenAlex_Single_Team();
    }
}

new OpenAlexTeamPlugin();