<?php
/**
 * OpenAlex Author Publications Scraper
 * Reemplazo legal y estable para GoogleScholarScraper
 * License: MIT
 */
class OpenAlexScraper
{
    private $ch = null;
    private $api_base = 'https://api.openalex.org';
    
    function __construct(){
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,  // ✅ SSL habilitado
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'TuProyecto/1.0 (tu@email.com)', // Requerido por OpenAlex
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_TIMEOUT => 30
        ]);
    }
    
    function __destruct(){
        curl_close($this->ch);
    }
    
    /**
     * Buscar autor por nombre y obtener su OpenAlex ID
     * @param string $nombre Nombre del autor
     * @param string $apellido Apellido del autor
     * @param string $institucion Opcional: filtrar por institución
     * @return array|null Datos del autor o null si no encontrado
     */
    function search_author($nombre, $apellido, $institucion = null)
    {
        $query = urlencode("$apellido, $nombre");
        $url = "{$this->api_base}/authors?filter=display_name.search:$query&per_page=5";
        
        if ($institucion) {
            $url .= "&filter=institutions.display_name.search:" . urlencode($institucion);
        }
        
        curl_setopt($this->ch, CURLOPT_URL, $url);
        $response = curl_exec($this->ch);
        $data = json_decode($response, true);
        
        if (isset($data['results']) && count($data['results']) > 0) {
            // Retornar el primer resultado (más relevante)
            return $data['results'][0];
        }
        
        return null;
    }
    
    /**
     * Obtener todas las publicaciones de un autor por su OpenAlex ID
     * @param string $openalex_id OpenAlex Author ID (ej: A5023889049)
     * @param int $limite Máximo de publicaciones a recuperar
     * @return array Lista de publicaciones
     */
    function get_author_works($openalex_id, $limite = 200)
    {
        $pubs = [];
        $url = "{$this->api_base}/works?filter=author.id:$openalex_id" .
               "&sort=publication_date:desc&per_page=200";
        
        $page = 1;
        while (count($pubs) < $limite) {
            curl_setopt($this->ch, CURLOPT_URL, $url . "&page=$page");
            $response = curl_exec($this->ch);
            $data = json_decode($response, true);
            
            if (!isset($data['results']) || count($data['results']) == 0) {
                break;
            }
            
            foreach ($data['results'] as $work) {
                $pubs[] = [
                    'id' => $work['id'] ?? null,
                    'doi' => $work['doi'] ?? null,
                    'title' => $work['title'] ?? null,
                    'year' => $work['publication_year'] ?? null,
                    'venue' => $work['primary_location']['source']['display_name'] ?? null,
                    'authors' => array_map(function($a) {
                        return $a['author']['display_name'] ?? null;
                    }, $work['authorships'] ?? []),
                    'citations' => $work['cited_by_count'] ?? 0,
                    'open_access' => $work['open_access']['is_oa'] ?? false,
                    'url' => $work['open_access']['oa_url'] ?? ($work['doi'] ? "https://doi.org/{$work['doi']}" : null)
                ];
                
                if (count($pubs) >= $limite) break;
            }
            
            // Verificar si hay más páginas
            if (count($data['results']) < 200) break;
            $page++;
            
            // Respetar rate limiting de OpenAlex (100 req/min sin API key)
            usleep(600000); // 0.6 segundos entre peticiones
        }
        
        return $pubs;
    }
    
    /**
     * Buscar autor por ORCID (más preciso que por nombre)
     * @param string $orcid ORCID iD (ej: 0000-0002-1825-0097)
     * @return array|null Datos del autor
     */
    function get_author_by_orcid($orcid)
    {
        $url = "{$this->api_base}/authors?filter=orcid:$orcid&per_page=1";
        curl_setopt($this->ch, CURLOPT_URL, $url);
        $response = curl_exec($this->ch);
        $data = json_decode($response, true);
        
        if (isset($data['results']) && count($data['results']) > 0) {
            return $data['results'][0];
        }
        
        return null;
    }
}

// ============================================
// FUNCIONES DE TEST (solo si se ejecuta directamente)
// ============================================
function test_scraper()
{
    // Mapeo de investigadores con ORCID o nombre para buscar
    $investigadores = [
        'Budan' => ['nombre' => 'Fernando', 'apellido' => 'Budan', 'orcid' => null],
        'Carballido' => ['nombre' => 'Juan', 'apellido' => 'Carballido', 'orcid' => null],
        'Chesñevar' => ['nombre' => 'Carlos', 'apellido' => 'Chesñevar', 'orcid' => '0000-0002-6795-6069'],
        'Simari' => ['nombre' => 'Guillermo', 'apellido' => 'Simari', 'orcid' => '0000-0002-3652-2849'],
    ];
    
    $scraper = new OpenAlexScraper;
    
    foreach ($investigadores as $apellido => $datos) {
        echo "\n=== Buscando: $apellido ===\n";
        
        // Intentar primero por ORCID (más preciso)
        if ($datos['orcid']) {
            echo "Buscando por ORCID: {$datos['orcid']}\n";
            $author = $scraper->get_author_by_orcid($datos['orcid']);
        } else {
            echo "Buscando por nombre: {$datos['nombre']} {$datos['apellido']}\n";
            $author = $scraper->search_author($datos['nombre'], $datos['apellido']);
        }
        
        if ($author) {
            echo "✅ Autor encontrado: {$author['display_name']}\n";
            echo "   OpenAlex ID: {$author['id']}\n";
            echo "   Trabajos totales: {$author['works_count']}\n";
            echo "   Citaciones: {$author['cited_by_count']}\n";
            
            // Obtener últimas 10 publicaciones
            $works = $scraper->get_author_works($author['id'], 10);
            echo "   Últimas publicaciones:\n";
            foreach (array_slice($works, 0, 5) as $w) {
                echo "   - ({$w['year']}) {$w['title']}\n";
            }
        } else {
            echo "❌ Autor no encontrado\n";
        }
        
        sleep(2); // Pausa entre investigadores
    }
}

// ✅ Solo ejecutar test si se llama directamente al archivo
if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') == __FILE__) {
    test_scraper();
}