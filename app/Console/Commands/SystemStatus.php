<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\CrawlJob;
use App\Models\Source;
use App\Models\VerificationRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SystemStatus extends Command
{
    protected $signature = 'system:status';

    protected $description = 'Display VerifySource system status and statistics';

    public function handle(): int
    {
        $this->displayHeader();
        $this->displaySourceStats();
        $this->displayCrawlJobStats();
        $this->displayArticleStats();
        $this->displayVerificationStats();
        $this->displayQueueHealth();
        $this->displayRecommendations();

        return self::SUCCESS;
    }

    protected function displayHeader(): void
    {
        $this->line('');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('           VerifySource System Status Dashboard           ');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->line('');
    }

    protected function displaySourceStats(): void
    {
        $totalSources = Source::count();
        $activeSources = Source::where('is_active', true)->count();
        $verifiedSources = Source::where('is_verified', true)->count();
        $recentlyCrawled = Source::where('last_crawled_at', '>=', now()->subHours(24))->count();

        $this->info('📰 News Sources:');
        $this->line("   Total Sources: {$totalSources}");
        $this->line("   Active Sources: {$activeSources}");
        $this->line("   Verified Sources: {$verifiedSources}");
        $this->line("   Crawled (24h): {$recentlyCrawled}");

        if ($activeSources === 0) {
            $this->warn('   ⚠️  No active sources! Run: php artisan tinker (see setup guide)');
        }

        $this->line('');
    }

    protected function displayCrawlJobStats(): void
    {
        $totalJobs = CrawlJob::count();
        $statusBreakdown = CrawlJob::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->info('🤖 Crawl Jobs:');
        $this->line("   Total Jobs: {$totalJobs}");

        foreach ($statusBreakdown as $status => $count) {
            $icon = match($status) {
                'pending' => '⏳',
                'processing' => '🔄',
                'completed' => '✅',
                'failed' => '❌',
                default => '•'
            };
            $this->line("   {$icon} {$status}: {$count}");
        }

        $recentJobs = CrawlJob::where('created_at', '>=', now()->subHour())->count();
        $this->line("   Created (1h): {$recentJobs}");

        if (isset($statusBreakdown['pending']) && $statusBreakdown['pending'] > 100) {
            $this->warn('   ⚠️  High pending queue! Check if queue workers are running.');
        }

        $this->line('');
    }

    protected function displayArticleStats(): void
    {
        $totalArticles = Article::count();
        $recentArticles = Article::where('created_at', '>=', now()->subDay())->count();
        $processedArticles = Article::where('is_processed', true)->count();
        $duplicates = Article::where('is_duplicate', true)->count();

        $topSources = DB::table('articles')
            ->join('sources', 'articles.source_id', '=', 'sources.id')
            ->selectRaw('sources.name, COUNT(*) as count')
            ->groupBy('sources.id', 'sources.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        $this->info('📄 Articles:');
        $this->line("   Total Articles: {$totalArticles}");
        $this->line("   Added (24h): {$recentArticles}");
        $this->line("   Processed: {$processedArticles}");
        $this->line("   Duplicates: {$duplicates}");

        if ($topSources->isNotEmpty()) {
            $this->line('');
            $this->line('   Top Sources by Articles:');
            foreach ($topSources as $source) {
                $this->line("     • {$source->name}: {$source->count}");
            }
        }

        if ($totalArticles === 0) {
            $this->warn('   ⚠️  No articles yet! Start services: start-services.bat');
        }

        $this->line('');
    }

    protected function displayVerificationStats(): void
    {
        $totalVerifications = VerificationRequest::count();
        $recentVerifications = VerificationRequest::where('created_at', '>=', now()->subDay())->count();
        $completedVerifications = VerificationRequest::where('status', 'completed')->count();
        $averageConfidence = VerificationRequest::where('status', 'completed')
            ->avg('confidence_score');

        $this->info('🔍 Verifications:');
        $this->line("   Total Requests: {$totalVerifications}");
        $this->line("   Verified (24h): {$recentVerifications}");
        $this->line("   Completed: {$completedVerifications}");

        if ($averageConfidence !== null) {
            $avgPercent = round($averageConfidence * 100, 1);
            $this->line("   Avg Confidence: {$avgPercent}%");
        }

        $this->line('');
    }

    protected function displayQueueHealth(): void
    {
        // Check if queue workers might be running by checking recent job updates
        $recentlyProcessed = CrawlJob::where('status', 'completed')
            ->where('updated_at', '>=', now()->subMinutes(30))
            ->count();

        $recentlyFailed = CrawlJob::where('status', 'failed')
            ->where('updated_at', '>=', now()->subMinutes(30))
            ->count();

        $this->info('⚡ System Health:');

        if ($recentlyProcessed > 0) {
            $this->line("   ✅ Queue Active: {$recentlyProcessed} jobs completed (30min)");
        } else {
            $this->warn('   ⚠️  Queue Inactive: No jobs completed recently');
            $this->warn('      Start workers: start-services.bat');
        }

        if ($recentlyFailed > 10) {
            $this->error("   ❌ High Failure Rate: {$recentlyFailed} jobs failed (30min)");
        }

        // Check scheduler
        $schedulerLog = \Illuminate\Support\Facades\Log::getLogger()
            ->getHandlers()[0] ?? null;

        $this->line('');
    }

    protected function displayRecommendations(): void
    {
        $this->info('💡 Recommendations:');

        $activeSources = Source::where('is_active', true)->count();
        $totalArticles = Article::count();
        $pendingJobs = CrawlJob::where('status', 'pending')->count();
        $recentlyProcessed = CrawlJob::where('status', 'completed')
            ->where('updated_at', '>=', now()->subMinutes(30))
            ->count();

        if ($activeSources === 0) {
            $this->line('   1. Add news sources (see AUTOMATIC-CRAWLING-SETUP.md)');
        }

        if ($pendingJobs > 0 && $recentlyProcessed === 0) {
            $this->line('   2. Start queue workers: start-services.bat');
        }

        if ($totalArticles === 0 && $activeSources > 0) {
            $this->line('   3. Trigger initial crawl: php artisan crawl:schedule --queue');
        }

        if ($totalArticles > 0) {
            $this->line('   ✅ System is operational! Articles available for verification.');
        }

        $this->line('');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->line('');
    }
}
