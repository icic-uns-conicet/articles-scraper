<?php
/**
 * Funciones auxiliares compartidas por todos los módulos.
 *
 * @package OpenAlexTeam
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class OpenAlex_Helpers {

    /**
     * Verifica que las clases de teachPress estén disponibles.
     */
    public static function teachpress_active(): bool {
        return class_exists( 'TP_Publications' ) && class_exists( 'TP_Authors' );
    }

    /**
     * Mapea los tipos de OpenAlex a los tipos BibTeX de teachPress.
     */
    public static function map_pub_type( string $type ): string {
        $map = [
            'article'             => 'article',
            'journal-article'     => 'article',
            'book-chapter'        => 'inbook',
            'book'                => 'book',
            'edited-book'         => 'book',
            'proceedings-article' => 'inproceedings',
            'conference-paper'    => 'inproceedings',
            'review'              => 'article',
            'dissertation'        => 'phdthesis',
            'thesis'              => 'phdthesis',
            'preprint'            => 'unpublished',
            'report'              => 'techreport',
            'dataset'             => 'misc',
            'other'               => 'misc',
        ];
        return $map[ $type ] ?? 'misc';
    }

    /**
     * Etiqueta legible para cada tipo de publicación.
     */
    public static function get_type_label( string $type ): string {
        $labels = [
            'article'       => 'Artículo',
            'inbook'        => 'Capítulo',
            'book'          => 'Libro',
            'inproceedings' => 'Conferencia',
            'phdthesis'     => 'Tesis',
            'techreport'    => 'Informe',
            'unpublished'   => 'Preprint',
            'misc'          => 'Misc',
        ];
        return $labels[ $type ] ?? ucfirst( $type );
    }

    /**
     * Convierte "John William Smith" → "Smith, John William".
     */
    public static function format_author_name( string $display_name ): string {
        $parts = preg_split( '/\s+/', trim( $display_name ) );
        if ( count( $parts ) < 2 ) return $display_name;
        $last = array_pop( $parts );
        return $last . ', ' . implode( ' ', $parts );
    }

    /**
     * Devuelve el apellido para el campo sort_name de teachPress.
     */
    public static function get_sort_name( string $display_name ): string {
        $parts = preg_split( '/\s+/', trim( $display_name ) );
        return array_pop( $parts ) ?? $display_name;
    }

    /**
     * Construye el string de autores "Smith, John and Doe, Jane and ..."
     * a partir del array de authorships de OpenAlex.
     */
    public static function build_author_string( array $authorships ): string {
        $names = [];
        foreach ( $authorships as $a ) {
            $name = $a['author']['display_name'] ?? '';
            if ( $name ) $names[] = self::format_author_name( $name );
        }
        return implode( ' and ', $names );
    }

    /**
     * Reconstruye el abstract desde el inverted index de OpenAlex.
     */
    public static function reconstruct_abstract( array $inverted_index ): string {
        $positions = [];
        foreach ( $inverted_index as $word => $pos_list ) {
            foreach ( $pos_list as $pos ) $positions[ $pos ] = $word;
        }
        ksort( $positions );
        return implode( ' ', $positions );
    }

    /**
     * Genera una clave BibTeX única a partir de autor, año y título.
     */
    public static function generate_bibtex_key( string $author_string, int $year, string $title ): string {
        $first_author = preg_replace( '/[^a-zA-Z]/', '', strtok( $author_string, ',' ) ?: 'unknown' );
        $stopwords    = [ 'a', 'an', 'the', 'of', 'in', 'on', 'at', 'to', 'and', 'or' ];
        $first_word   = '';
        foreach ( preg_split( '/\s+/', $title ) as $w ) {
            $clean = preg_replace( '/[^a-zA-Z]/', '', strtolower( $w ) );
            if ( $clean && ! in_array( $clean, $stopwords, true ) ) {
                $first_word = ucfirst( $clean );
                break;
            }
        }
        return strtolower( $first_author ) . ( $year ?: 'nd' ) . $first_word;
    }

    /**
     * Formatea el listado de autores para la vista pública.
     * "Smith, John and Doe, Jane" → "Smith J., Doe J." (máx 5, luego et al.)
     */
    public static function format_author_list( string $author_string ): string {
        $names = explode( ' and ', $author_string );
        $short = [];
        foreach ( array_slice( $names, 0, 5 ) as $name ) {
            $parts    = explode( ',', $name, 2 );
            $last     = trim( $parts[0] );
            $first    = isset( $parts[1] ) ? trim( $parts[1] ) : '';
            $initials = '';
            if ( $first ) {
                foreach ( preg_split( '/\s+/', $first ) as $part ) {
                    if ( $part ) $initials .= mb_strtoupper( mb_substr( $part, 0, 1 ) ) . '.';
                }
            }
            $short[] = $last . ( $initials ? ' ' . $initials : '' );
        }
        $result = implode( ', ', $short );
        if ( count( $names ) > 5 ) $result .= ' et al.';
        return $result;
    }

    /**
     * Obtiene las publicaciones de teachPress asociadas a un miembro del team.
     */
    public static function get_member_publications( int $post_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.pub_id, p.title, p.type, DATE_FORMAT(p.date,'%%Y') AS year,
                    p.doi, p.url, p.author, p.journal
             FROM {$wpdb->prefix}teachpress_pub p
             INNER JOIN {$wpdb->prefix}teachpress_pub_meta m
                 ON m.pub_id = p.pub_id
             WHERE m.meta_key = 'openalex_member_id' AND m.meta_value = %s
             ORDER BY p.date DESC",
            $post_id
        ) );
    }
}
