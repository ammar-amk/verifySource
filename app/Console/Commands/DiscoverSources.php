<?php

namespace App\Console\Commands;

use App\Services\SourceDiscoveryService;
use Illuminate\Console\Command;

class DiscoverSources extends Command
{
    protected $signature = 'sources:discover 
                            {--limit=50 : Maximum number of new sources to discover}
                            {--score : Calculate credibility scores}
                            {--auto-activate : Automatically activate discovered sources}
                            {--dry-run : Preview discoveries without creating}';

    protected $description = 'Autonomously discover, validate, score, and onboard new news sources';

    public function handle(SourceDiscoveryService $discoveryService): int
    {
        $this->info('🔍 Starting autonomous source discovery...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No sources will be created');
        }

        try {
            $result = $discoveryService->discoverAndOnboardSources([
                'limit' => (int) $this->option('limit'),
                'dry_run' => $dryRun,
            ]);

            if (!$result['success']) {
                $this->error('❌ Source discovery failed: ' . $result['error']);
                return self::FAILURE;
            }

            $this->displayResults($result);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Unexpected error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function displayResults(array $result): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('SOURCE DISCOVERY RESULTS');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        // Summary
        $this->line('📊 Summary:');
        $this->line('  • Discovered: ' . count($result['discovered']) . ' sources');
        $this->line('  • Validated: ' . count($result['validated']) . ' sources');
        $this->line('  • Created: ' . count($result['created']) . ' new sources');
        $this->newLine();

        // Created sources details
        if (!empty($result['created'])) {
            $this->info('✅ Newly Created Sources:');
            $this->newLine();

            $headers = ['ID', 'Name', 'Domain', 'Credibility Score'];
            $rows = array_map(function ($source) {
                return [
                    $source['source_id'],
                    $source['name'],
                    $source['domain'],
                    round($source['credibility_score'], 2) . '%',
                ];
            }, $result['created']);

            $this->table($headers, $rows);
            $this->newLine();
        }

        // Discovered but not validated
        $notValidated = count($result['discovered']) - count($result['validated']);
        if ($notValidated > 0) {
            $this->warn("⚠️  {$notValidated} sources discovered but failed validation");
            $this->newLine();
            
            // Show validation failures if available
            if (!empty($result['failures'])) {
                $this->line('📋 Validation Failure Details:');
                $this->newLine();
                
                $failureHeaders = ['Domain', 'Name', 'Failure Reason'];
                $failureRows = [];
                
                foreach ($result['failures'] as $domain => $failure) {
                    $failureRows[] = [
                        $domain,
                        $failure['name'],
                        $failure['reason'],
                    ];
                }
                
                $this->table($failureHeaders, $failureRows);
                $this->newLine();
            }
        }

        // Auto-crawl started
        $this->info("🕷️  Crawling will automatically begin for newly discovered sources");
        $this->line('   Check crawl status with: php artisan crawl:status');
        $this->newLine();

        $this->info('═══════════════════════════════════════════════════════');
        $this->info('Timestamp: ' . $result['timestamp']);
        $this->info('═══════════════════════════════════════════════════════');
    }
}
