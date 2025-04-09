<?php

namespace namhuunam\ImdbSync\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use namhuunam\ImdbSync\Exceptions\OmdbApiException;
use namhuunam\ImdbSync\Services\OmdbApiService;

class SyncImdbRatings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'imdb:sync 
                          {--limit=50 : Maximum number of movies to process}
                          {--force : Force sync even for already synced movies}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync movie ratings from OMDB API';

    /**
     * @var OmdbApiService
     */
    protected $omdbService;

    /**
     * Create a new command instance.
     *
     * @param OmdbApiService $omdbService
     */
    public function __construct(OmdbApiService $omdbService)
    {
        parent::__construct();
        $this->omdbService = $omdbService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting IMDB rating synchronization...');
        
        // Check if sync_imdb column exists, if not create it
        $this->ensureSyncColumnExists();
        
        // Get movies that need to be synced
        $limit = $this->option('limit');
        $force = $this->option('force');
        
        $query = DB::table('movies');
        
        if (!$force) {
            $query->where('sync_imdb', 0);
        }
        
        $movies = $query->take($limit)->get();
        
        if ($movies->isEmpty()) {
            $this->info('No movies to sync. Exiting...');
            return 0;
        }
        
        $this->info("Found {$movies->count()} movies to sync.");
        $bar = $this->output->createProgressBar($movies->count());
        
        foreach ($movies as $movie) {
            try {
                $this->syncMovie($movie);
                $bar->advance();
                
                // Add delay between API calls
                sleep(config('imdb-sync.api_request_delay', 1));
                
            } catch (OmdbApiException $e) {
                $this->error("Failed to sync all movies: " . $e->getMessage());
                Log::channel(config('imdb-sync.log_channel'))->error('IMDB sync failed: ' . $e->getMessage());
                $bar->finish();
                $this->newLine();
                return 1;
            }
        }
        
        $bar->finish();
        $this->newLine();
        $this->info('IMDB rating synchronization completed!');
        
        return 0;
    }
    
    /**
     * Make sure the sync_imdb column exists in the movies table
     *
     * @return void
     */
    protected function ensureSyncColumnExists()
    {
        if (!Schema::hasColumn('movies', 'sync_imdb')) {
            $this->info('Adding sync_imdb column to movies table...');
            
            Schema::table('movies', function (Blueprint $table) {
                $table->boolean('sync_imdb')->default(0);
            });
        }
    }
    
    /**
     * Sync a single movie with OMDB
     *
     * @param object $movie
     * @return void
     * @throws OmdbApiException
     */
    protected function syncMovie($movie)
    {
        // Get title and year
        $title = $movie->origin_name ?? '';
        $year = $movie->publish_year ?? '';
        
        if (empty($title)) {
            // Skip movies without titles
            DB::table('movies')->where('id', $movie->id)->update(['sync_imdb' => 1]);
            return;
        }
        
        // Get movie data from OMDB API
        $movieData = $this->omdbService->getMovieDetails($title, $year);
        
        // Initialize update data
        $updateData = [
            'sync_imdb' => 1,
        ];
        
        if (isset($movieData['Response']) && $movieData['Response'] === 'True') {
            // Successful response
            // Handle rating star
            if (isset($movieData['imdbRating']) && $movieData['imdbRating'] !== 'N/A') {
                $updateData['rating_star'] = (float) $movieData['imdbRating'];
            } else {
                $updateData['rating_star'] = $this->getRandomRating();
            }
            
            // Handle vote count
            if (isset($movieData['imdbVotes']) && $movieData['imdbVotes'] !== 'N/A') {
                // Remove commas from vote count
                $votes = str_replace(',', '', $movieData['imdbVotes']);
                $updateData['rating_count'] = (int) $votes;
            } else {
                $updateData['rating_count'] = $this->getRandomVoteCount();
            }
        } else {
            // Movie not found or other error
            if (isset($movieData['Error']) && $movieData['Error'] === 'Incorrect IMDb ID.') {
                // Xử lý riêng cho lỗi "Incorrect IMDb ID"
                $updateData['rating_star'] = $this->getRandomRatingForIncorrectId();
                $updateData['rating_count'] = $this->getRandomVoteCount();
                
                // Ghi log cho trường hợp lỗi này
                Log::channel(config('imdb-sync.log_channel'))->info("Movie ID {$movie->id} ({$title}, {$year}) has incorrect IMDb ID. Using random values.");
            } else {
                // Các lỗi khác sử dụng cấu hình mặc định
                $updateData['rating_star'] = $this->getRandomRating();
                $updateData['rating_count'] = $this->getRandomVoteCount();
            }
        }
        
        // Update the database
        DB::table('movies')->where('id', $movie->id)->update($updateData);
    }
    
    /**
     * Get a random rating in the configured range
     *
     * @return float
     */
    protected function getRandomRating()
    {
        $min = config('imdb-sync.random_rating_min', 6.0);
        $max = config('imdb-sync.random_rating_max', 8.0);
        return round(mt_rand($min * 10, $max * 10) / 10, 1);
    }
    
    /**
     * Get a random rating for incorrect IMDb ID case
     *
     * @return float
     */
    protected function getRandomRatingForIncorrectId()
    {
        // Sử dụng giá trị cụ thể cho trường hợp "Incorrect IMDb ID"
        $min = 5.0;
        $max = 8.0;
        return round(mt_rand($min * 10, $max * 10) / 10, 1);
    }
    
    /**
     * Get a random vote count in the configured range
     *
     * @return int
     */
    protected function getRandomVoteCount()
    {
        $min = config('imdb-sync.random_votes_min', 600);
        $max = config('imdb-sync.random_votes_max', 1000);
        return mt_rand($min, $max);
    }
}
