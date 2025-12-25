<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
| Define the application's scheduled tasks here. The Laravel scheduler
| allows you to define command schedules within the application itself.
| Run "php artisan schedule:work" to process scheduled tasks.
*/

// Queue pending crawl jobs every 10 minutes
Schedule::command('crawl:schedule --queue --limit=50')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground()
    ->name('queue-pending-crawls')
    ->description('Queue pending crawl jobs for processing');

// Schedule all active sources daily at 2 AM
Schedule::command('crawl:schedule --frequency=daily')
    ->dailyAt('02:00')
    ->timezone('UTC')
    ->name('schedule-daily-crawls')
    ->description('Schedule daily crawls for all active sources');

// Process Python crawler scheduler every hour
Schedule::command('crawl:python:scheduler')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->name('python-crawler-scheduler')
    ->description('Process crawls using Python crawler');

// Autonomously discover and onboard new sources daily at 1 AM
Schedule::command('sources:discover --limit=20')
    ->dailyAt('01:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->runInBackground()
    ->name('discover-new-sources')
    ->description('Autonomously discover, validate, score, and onboard new news sources');

// Clean up old crawl jobs weekly
Schedule::command('crawl:cleanup --days=30')
    ->weekly()
    ->sundays()
    ->at('03:00')
    ->name('cleanup-old-crawls')
    ->description('Clean up crawl jobs older than 30 days');

// Log scheduler health check
Schedule::call(function () {
    \Illuminate\Support\Facades\Log::info('Scheduler is running', [
        'timestamp' => now()->toISOString(),
        'pending_jobs' => \App\Models\CrawlJob::where('status', 'pending')->count(),
        'active_sources' => \App\Models\Source::where('is_active', true)->count(),
    ]);
})->everyThirtyMinutes()
    ->name('scheduler-health-check')
    ->description('Log scheduler health status');
