<?php

namespace App\Console\Commands;

use App\Services\SourceManagementService;
use Illuminate\Console\Command;

class RefreshCredibilityCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credibility:refresh {--limit=50 : Number of sources to refresh} {--all : Refresh all sources regardless of last check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh credibility scores for sources';

    protected SourceManagementService $sourceManagement;

    public function __construct(SourceManagementService $sourceManagement)
    {
        parent::__construct();
        $this->sourceManagement = $sourceManagement;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $refreshAll = $this->option('all');

        $this->info("Starting credibility refresh for up to {$limit} sources...");

        if ($refreshAll) {
            $this->warn('Refreshing ALL sources (ignoring last check dates)');
        }

        try {
            $results = $this->sourceManagement->batchRefreshCredibility($limit, $refreshAll);

            $this->info('Credibility refresh completed:');
            $this->line("  Total sources processed: {$results['summary']['processed']}");
            $this->line("  Errors: {$results['summary']['errors']}");

            if ($results['summary']['errors'] > 0) {
                $this->warn('Some sources had errors during credibility refresh:');

                $errorResults = array_filter($results['results'], fn ($r) => $r['status'] === 'error');

                $headers = ['Source ID', 'Domain', 'Error'];
                $rows = array_map(fn ($r) => [$r['source_id'], $r['domain'], $r['error']], $errorResults);

                $this->table($headers, array_slice($rows, 0, 10)); // Show first 10 errors

                if (count($errorResults) > 10) {
                    $this->line('... and '.(count($errorResults) - 10).' more errors');
                }
            }

            if ($results['summary']['processed'] > 0) {
                $this->info('Successfully refreshed sources:');

                $successResults = array_filter($results['results'], fn ($r) => $r['status'] === 'success');

                $headers = ['Source ID', 'Domain', 'Credibility Score', 'Level'];
                $rows = array_map(fn ($r) => [
                    $r['source_id'],
                    $r['domain'],
                    $r['credibility_score'],
                    $r['credibility_level'],
                ], $successResults);

                $this->table($headers, array_slice($rows, 0, 20)); // Show first 20 successful refreshes

                if (count($successResults) > 20) {
                    $this->line('... and '.(count($successResults) - 20).' more successful refreshes');
                }
            }

        } catch (\Exception $e) {
            $this->error('Failed to refresh credibility: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
