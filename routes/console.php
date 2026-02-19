<?php

declare(strict_types=1);

use App\Console\Commands\PdfImportCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Register PDF import command
Artisan::command('schedule:list', function () {
    $this->info('Scheduled commands: none in v1.0 (use pg_cron or external cron)');
});

// Schedule queue processing (for non-serverless environments)
Schedule::command('queue:work --once --queue=default')->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
