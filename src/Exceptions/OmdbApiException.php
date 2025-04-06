<?php

namespace namhuunam\ImdbSync\Exceptions;

use Exception;

class OmdbApiException extends Exception
{
    protected $apiKey;
    
    public function __construct($message, $apiKey = null, $code = 0)
    {
        $this->apiKey = $apiKey;
        parent::__construct($message, $code);
    }
    
    public function getApiKey()
    {
        return $this->apiKey;
    }
}