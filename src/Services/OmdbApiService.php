<?php

namespace namhuunam\ImdbSync\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use namhuunam\ImdbSync\Exceptions\OmdbApiException;

class OmdbApiService
{
    /**
     * @var array
     */
    protected $apiKeys = [];
    
    /**
     * @var int
     */
    protected $currentKeyIndex = 0;
    
    /**
     * @var Client
     */
    protected $client;

    /**
     * OmdbApiService constructor.
     *
     * @param string $apiKeys Comma-separated API keys
     */
    public function __construct($apiKeys)
    {
        $this->apiKeys = is_string($apiKeys) 
            ? explode(',', $apiKeys) 
            : (is_array($apiKeys) ? $apiKeys : []);
            
        $this->client = new Client([
            'base_uri' => 'https://www.omdbapi.com/',
            'timeout'  => 10.0,
        ]);
    }

    /**
     * Get movie details from OMDB API
     *
     * @param string $title Movie title
     * @param string|int $year Movie release year
     * @return array Movie details
     * @throws OmdbApiException If all API keys failed
     */
    public function getMovieDetails($title, $year)
    {
        // Sanitize title by removing special characters
        $sanitizedTitle = $this->sanitizeTitle($title);
        
        // Track tried API keys to avoid infinite loops
        $triedKeys = [];
        
        while (count($triedKeys) < count($this->apiKeys)) {
            $apiKey = $this->getCurrentApiKey();
            $triedKeys[$apiKey] = true;
            
            try {
                $response = $this->client->get('', [
                    'query' => [
                        'apikey' => $apiKey,
                        't' => $sanitizedTitle,
                        'y' => $year,
                    ]
                ]);
                
                $data = json_decode($response->getBody()->getContents(), true);
                
                // Check for API errors that aren't "Movie not found" or "Incorrect IMDb ID"
                if (isset($data['Response']) && $data['Response'] === 'False') {
                    if (isset($data['Error']) && 
                        $data['Error'] !== 'Movie not found!' && 
                        $data['Error'] !== 'Incorrect IMDb ID.') {
                        $this->logApiError($apiKey, $sanitizedTitle, $year, $data['Error']);
                        $this->rotateApiKey();
                        continue;
                    }
                    
                    // Ghi log cho trường hợp Incorrect IMDb ID nhưng vẫn trả về dữ liệu
                    if (isset($data['Error']) && $data['Error'] === 'Incorrect IMDb ID.') {
                        $this->logApiError($apiKey, $sanitizedTitle, $year, $data['Error']);
                        // Vẫn trả về dữ liệu để xử lý ở lớp trên
                    }
                }
                
                return $data;
                
            } catch (GuzzleException $e) {
                $this->logApiError($apiKey, $sanitizedTitle, $year, $e->getMessage());
                $this->rotateApiKey();
            }
        }
        
        // If we get here, all API keys have failed
        throw new OmdbApiException('All API keys failed to retrieve movie data', null);
    }
    
    /**
     * Sanitize movie title by removing special characters
     *
     * @param string $title
     * @return string
     */
    protected function sanitizeTitle($title)
    {
        if (empty($title)) {
            return '';
        }
        
        // Remove special characters like parentheses, brackets, etc.
        $sanitized = preg_replace('/[\(\)\[\]\{\}\<\>\'\"\?\!\:\;\-\_\+\=\@\#\$\%\^\&\*\~\|\\\\\/]/', ' ', $title);
        
        // Replace multiple spaces with a single space
        $sanitized = preg_replace('/\s+/', ' ', $sanitized);
        
        // Trim whitespace from the beginning and end
        return trim($sanitized);
    }
    
    /**
     * Get the current API key
     *
     * @return string|null
     */
    protected function getCurrentApiKey()
    {
        if (empty($this->apiKeys)) {
            return null;
        }
        
        return $this->apiKeys[$this->currentKeyIndex];
    }
    
    /**
     * Rotate to the next API key
     */
    protected function rotateApiKey()
    {
        $this->currentKeyIndex = ($this->currentKeyIndex + 1) % count($this->apiKeys);
    }
    
    /**
     * Log API errors
     *
     * @param string $apiKey
     * @param string $title
     * @param string|int $year
     * @param string $error
     */
    protected function logApiError($apiKey, $title, $year, $error)
    {
        $logMessage = "OMDB API Error for movie '$title' ($year) with API key '$apiKey': $error";
        Log::channel(config('imdb-sync.log_channel'))->error($logMessage);
    }
}
