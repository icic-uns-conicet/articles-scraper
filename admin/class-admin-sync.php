<?php
/**
 * Handler del formulario de sincronización (admin-post).
 *
 * Recibe la acción POST, llama a OpenAlex_TeachPress_Import::sync_member()
 * y redirige con el resultado almacenado en un transient.
 *
 * @package OpenAlexTeam
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OpenAlex_Admin_Sync {

    public function __construct() {
        add_action( 'admin_post_openalex_sync', [ $this, 'handle_sync' ] );
    }

    public function handle_sync(): void {
        $post_id = intval( $_POST['post_id'] ?? 0 );

        if ( ! $post_id || ! wp_verify_nonce( $_POST['openalex_sync_nonce'] ?? '', 'openalex_sync_' . $post_id ) ) {
            wp_die( 'Solicitud no válida.', 403 );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Sin permisos.', 403 );
        }
        if ( ! OpenAlex_Helpers::teachpress_active() ) {
            wp_die( 'teachPress no está activo. Activalo antes de sincronizar.', 400 );
        }

        $result = OpenAlex_TeachPress_Import::sync_member( $post_id );
		OpenAlex_Helpers::clear_member_publications_cache( $post_id );

        // Guardar resultado para mostrarlo en el redirect
        set_transient( 'openalex_sync_result_' . get_current_user_id(), $result, 60 );

        $is_detail  = ( ( $_POST['redirect'] ?? '' ) === 'detail' );
        $redirect   = $is_detail
            ? admin_url( 'admin.php?page=openalex-publications&post_id=' . $post_id )
            : admin_url( 'admin.php?page=openalex-publications' );

        wp_redirect( $redirect );
        exit;
    }
}
