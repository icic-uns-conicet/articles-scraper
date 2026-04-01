<?php
/**
 * OpenAlex Author Publications Scraper
 * Reemplazo legal y estable para GoogleScholarScraper
 * License: MIT
 */

// ============================================
// CARGA DE VARIABLES DE ENTORNO
// ============================================
function load_env($path)
{
    if (!file_exists($path)) {
        throw new Exception("El archivo .env no existe en: $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

class OpenAlexScraper
{
    private $ch = null;
    private $api_base = 'https://api.openalex.org';
    private $api_key = ""; // Opcional: tu API key de OpenAlex para mayor rate limit
    
    function __construct($api_key = null){
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,  // ✅ SSL habilitado
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'TuProyecto/1.0 (tu@email.com)', // Requerido por OpenAlex
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_TIMEOUT => 30
        ]);

        if (isset($_ENV["OPENALEX_API_KEY"])) {
            print("🔑 API key de OpenAlex cargada desde .env");
            $this->api_key = $_ENV["OPENALEX_API_KEY"];
        }
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
        $url = "{$this->api_base}/autocomplete/authors?q=$query&per_page=5&api_key={$this->api_key}";
        
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
    function get_author_works($openalex_id, $limite = 200)    {
        $pubs = [];
        $url = "{$this->api_base}/works?filter=author.id:$openalex_id" .
               "&sort=publication_date:desc&per_page=200" .
               "&api_key={$this->api_key}";
        
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
    function get_author_by_orcid($orcid)    {
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
        'Budan' => ['nombre' => 'Maximiliano', 'apellido' => 'Budan', 'orcid' => null],
        'Carballido' => ['nombre' => 'Jessica', 'apellido' => 'Carballido', 'orcid' => null],
        'Chesñevar' => ['nombre' => 'Carlos', 'apellido' => 'Chesñevar', 'orcid' => '0000-0002-1747-5905'],
        'Simari' => ['nombre' => 'Guillermo', 'apellido' => 'Simari', 'orcid' => '0000-0001-6247-0428'],
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
    // Cargar .env al inicio
    load_env(__DIR__ . '/.env');
    test_scraper();
}