<?php
/**
 * OpenAlex Author Publications Scraper
 * Legal and stable replacement for GoogleScholarScraper
 * License: MIT
 */

// ============================================
// LOADING ENVIRONMENT VARIABLES
// ============================================
function load_env($path)
{
    if (!file_exists($path)) {
        throw new Exception("The .env file does not exist at: $path");
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
    private $api_key = ""; // Optional: your OpenAlex API key for higher rate limits
    
    function __construct($api_key = null){
        $this->ch = curl_init();
        curl_setopt_array($this->ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,  // ✅ SSL enabled
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => $_ENV["USER_AGENT"] ?? 'YourProject/1.0 (your@email.com)', // Required by OpenAlex
            CURLOPT_ENCODING => 'gzip',
            // CURLOPT_VERBOSE => true,
            CURLOPT_TIMEOUT => 30
        ]);

        if (isset($_ENV["OPENALEX_API_KEY"])) {
            print("🔑 OpenAlex API key loaded from .env");
            $this->api_key = $_ENV["OPENALEX_API_KEY"];
        }
    }
    
    function __destruct(){
        curl_close($this->ch);
    }
    
    /**
     * Search for an author by name and get their OpenAlex ID
     * @param string $firstName Author's first name
     * @param string $lastName Author's last name
     * @param string $institution Optional: filter by institution
     * @return array|null Author data or null if not found
     */
    function search_author($firstName, $lastName)
    {
        $query = urlencode("$firstName, $lastName");
        $url = "{$this->api_base}/autocomplete/authors?q=$query&api_key={$this->api_key}";
                
        curl_setopt($this->ch, CURLOPT_URL, $url);
        // print($url . "\n");
        $response = curl_exec($this->ch);
        // print($response . "\n");
        $data = json_decode($response, true);
        // print_r($data);
        if (isset($data['results']) && count($data['results']) > 0) {
            // Return the first result (most relevant)
            return $data['results'][0];
        }
        
        return null;
    }
    
    /**
     * Get all publications of an author by their OpenAlex ID
     * @param string $openalex_id OpenAlex Author ID (e.g., A5023889049)
     * @param int $limit Maximum number of publications to retrieve
     * @return array List of publications
     */
    function get_author_works($openalex_id, $limit = 200)    {
        $pubs = [];
        $url = "{$this->api_base}/works?filter=author.id:$openalex_id" .
               "&sort=publication_date:desc&per_page=200" .
               "&api_key={$this->api_key}";
        
        $page = 1;
        while (count($pubs) < $limit) {
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
                
                if (count($pubs) >= $limit) break;
            }
            
            // Check if there are more pages
            if (count($data['results']) < 200) break;
            $page++;
            
            // Respect OpenAlex rate limiting (100 req/min without API key)
            usleep(600000); // 0.6 seconds between requests
        }
        
        return $pubs;
    }
    
    /**
     * Search for an author by ORCID (more precise than by name)
     * @param string $orcid ORCID iD (e.g., 0000-0002-1825-0097)
     * @return array|null Author data
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
// TEST FUNCTIONS (only if executed directly)
// ============================================
function test_scraper()
{
    // Mapping of researchers with ORCID or name to search
    $researchers = [
        'Budan' => ['firstName' => 'Maximiliano', 'lastName' => 'Budan', 'orcid' => null],
        'Carballido' => ['firstName' => 'Jessica', 'lastName' => 'Carballido', 'orcid' => null],
        'Chesñevar' => ['firstName' => 'Carlos', 'lastName' => 'Chesñevar', 'orcid' => '0000-0002-1747-5905'],
        'Simari' => ['firstName' => 'Guillermo', 'lastName' => 'Simari', 'orcid' => '0000-0001-6247-0428'],
    ];
    
    $scraper = new OpenAlexScraper;
    
    foreach ($researchers as $lastName => $data) {
        echo "\n=== Searching: $lastName ===\n";
        
        // Try first by ORCID (more precise)
        if ($data['orcid']) {
            echo "Searching by ORCID: {$data['orcid']}\n";
            $author = $scraper->get_author_by_orcid($data['orcid']);
        } else {
            echo "Searching by name: {$data['firstName']} {$data['lastName']}\n";
            $author = $scraper->search_author($data['firstName'], $data['lastName']);
        }
        
        if ($author) {
            echo "✅ Author found: {$author['display_name']}\n";
            echo "   OpenAlex ID: {$author['id']}\n";
            echo "   Total works: {$author['works_count']}\n";
            echo "   Citations: {$author['cited_by_count']}\n";
            
            // Get the last 10 publications
            $works = $scraper->get_author_works($author['id'], 10);
            echo "   Latest publications:\n";
            foreach (array_slice($works, 0, 5) as $w) {
                echo "   - ({$w['year']}) {$w['title']}\n";
            }
        } else {
            echo "❌ Author not found\n";
        }
        
        sleep(2); // Pause between researchers
    }
}

// ✅ Only execute test if called directly
if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') == __FILE__) {
    // Load .env at the start
    load_env(__DIR__ . '/.env');
    test_scraper();
}