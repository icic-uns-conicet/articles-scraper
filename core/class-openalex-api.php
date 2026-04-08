<?php
/**
 * Comunicación con la API de OpenAlex.
 *
 * Recupera works de un autor usando paginación cursor-based.
 *
 * @package OpenAlexTeam
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OpenAlex_API {

    /** Máximo de páginas a recuperar (200 works/página → 2000 works máx) */
    const MAX_PAGES = 10;

    /** Works por página (máximo permitido por OpenAlex) */
    const PER_PAGE = 200;

    /** Campos solicitados en cada request */
    const SELECT_FIELDS = 'id,title,type,publication_year,doi,authorships,biblio,primary_location,abstract_inverted_index';

    /**
     * Recupera todos los works de un autor.
     *
     * @param  string $openalex_author_id  ID de OpenAlex, ej: "A123456789"
     * @return array{works: array, errors: string[]}
     */
    public static function fetch_works( string $openalex_author_id ): array {
        $author_id = basename( trim( $openalex_author_id ) );
        $works     = [];
        $errors    = [];
        $cursor    = '*';

        for ( $page = 0; $page < self::MAX_PAGES; $page++ ) {
            $url = add_query_arg( [
                'filter'   => "author.id:{$author_id}",
                'per-page' => self::PER_PAGE,
                'cursor'   => $cursor,
                'select'   => self::SELECT_FIELDS,
            ], 'https://api.openalex.org/works' );

            $response = wp_remote_get( $url, [ 'timeout' => 30 ] );

            if ( is_wp_error( $response ) ) {
                $errors[] = 'Error de conexión: ' . $response->get_error_message();
                break;
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( empty( $body['results'] ) ) break;

            $works  = array_merge( $works, $body['results'] );
            $cursor = $body['meta']['next_cursor'] ?? null;

            if ( ! $cursor ) break;
        }

        return compact( 'works', 'errors' );
    }
}
