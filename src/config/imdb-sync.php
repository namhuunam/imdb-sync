<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OMDB API Keys
    |--------------------------------------------------------------------------
    |
    | Here you can define your OMDB API keys as a comma-separated list
    | Example: '67bd4c17,945411bc,78a86024'
    |
    */
    'api_keys' => env('APIKEY_OMDB', ''),

    /*
    |--------------------------------------------------------------------------
    | Delay Between API Requests (in seconds)
    |--------------------------------------------------------------------------
    |
    */
    'api_request_delay' => 1,

    /*
    |--------------------------------------------------------------------------
    | Random Rating Range for Missing Ratings
    |--------------------------------------------------------------------------
    |
    | Define the range for random ratings and votes when OMDB doesn't provide values
    |
    */
    'random_rating_min' => 6.0,
    'random_rating_max' => 8.0,
    'random_votes_min' => 600,
    'random_votes_max' => 1000,

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | Specify which log channel to use for logging API errors
    |
    */
    'log_channel' => env('IMDB_SYNC_LOG_CHANNEL', 'daily'),
];