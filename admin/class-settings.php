<?php
/**
 * Settings del plugin OpenAlex Team.
 *
 * @package OpenAlexTeam
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OpenAlex_Settings {

    const OPTION_KEY = 'openalex_team_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_submenu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_submenu(): void {
        add_submenu_page(
            'edit.php?post_type=team',
            'OpenAlex Settings',
            'OpenAlex Settings',
            'manage_options',
            'openalex-team-settings',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        register_setting(
            'openalex_team_settings_group',
            self::OPTION_KEY,
            [ $this, 'sanitize_settings' ]
        );

        add_settings_section(
            'openalex_team_api_section',
            'API de OpenAlex',
            function () {
                echo '<p>Configurá la API key para autenticar las requests a OpenAlex.</p>';
            },
            'openalex-team-settings'
        );

        add_settings_field(
            'api_key',
            'API Key',
            [ $this, 'render_api_key_field' ],
            'openalex-team-settings',
            'openalex_team_api_section'
        );

        add_settings_field(
            'mailto',
            'Email de contacto',
            [ $this, 'render_mailto_field' ],
            'openalex-team-settings',
            'openalex_team_api_section'
        );
    }

    public function sanitize_settings( array $input ): array {
        return [
            'api_key' => sanitize_text_field( $input['api_key'] ?? '' ),
            'mailto'  => sanitize_email( $input['mailto'] ?? '' ),
        ];
    }

    public function render_api_key_field(): void {
        $options = get_option( self::OPTION_KEY, [] );
        $value   = $options['api_key'] ?? '';
        printf(
            '<input type="text" name="%s[api_key]" value="%s" class="regular-text" autocomplete="off" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $value )
        );
        echo '<p class="description">Se agrega como <code>api_key</code> en cada request a OpenAlex.</p>';
    }

    public function render_mailto_field(): void {
        $options = get_option( self::OPTION_KEY, [] );
        $value   = $options['mailto'] ?? '';
        printf(
            '<input type="email" name="%s[mailto]" value="%s" class="regular-text" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $value )
        );
        echo '<p class="description">Opcional. Email de contacto para OpenAlex.</p>';
    }

    public function render_page(): void {
        ?>
        <div class="wrap">
            <h1>OpenAlex Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'openalex_team_settings_group' );
                do_settings_sections( 'openalex-team-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function get_api_key(): string {
        $options = get_option( self::OPTION_KEY, [] );
        return (string) ( $options['api_key'] ?? '' );
    }

    public static function get_mailto(): string {
        $options = get_option( self::OPTION_KEY, [] );
        return (string) ( $options['mailto'] ?? '' );
    }
}