# Cài đặt imdb-sync
# 1 cài đặt
```bash
# Thêm repository vào composer.json
composer config repositories.imdb-sync vcs https://github.com/namhuunam/imdb-sync.git

# Cài đặt package
composer require namhuunam/imdb-sync
```
# 2 Add the required API keys to your .env file:
```bash
APIKEY_OMDB=67bd4c17,945411bc,78a86024,47b94c53,fad1abb,88ea574e,cf3bfd86,b4a11e90,e3e87ea2,45adcf81,d872382d,e6d1552d,8ad484ee,982646b7,21dd9d5f,57fbccb2,e4b686f0,66e65879,828f922f,c44adf85,4d521c7e,3763fcc5,141c5155,fbb7b3a0,68072fbd,fda519a7
```
# 3 Register the package in your Laravel application's config/app.php:
```bash
'providers' => [
    // Other service providers...
    YourUsername\ImdbSync\Providers\ImdbSyncServiceProvider::class,
],
```
# 4 Publish the configuration:
```bash
php artisan vendor:publish --provider="namhuunam\ImdbSync\Providers\ImdbSyncServiceProvider" --tag="config"
```
# 5 Run the migration to add the sync_imdb column:
```bash
php artisan migrate
```
# Using the Package
Manual Execution
You can run the synchronization manually with:
```bash
php artisan imdb:sync
```
Options:

--limit=50: Specify how many movies to process per run
--force: Force sync even for already synced movies
Scheduling the Command
To set up a cronjob, add the following to your application's app/Console/Kernel.php file:
```bash
protected function schedule(Schedule $schedule)
{
    // Other scheduled tasks...
    
    // Run IMDB sync daily at midnight
    $schedule->command('imdb:sync --limit=100')->daily();
    
    // Or run every hour
    // $schedule->command('imdb:sync --limit=20')->hourly();
}
```
Make sure your Laravel scheduler is set up in your server's crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```
Additional Features
Logging
The package logs all API errors to the channel specified in the config file. By default, it uses Laravel's 'daily' log channel. You can view the logs with:
```bash
tail -f storage/logs/laravel-*.log
```
