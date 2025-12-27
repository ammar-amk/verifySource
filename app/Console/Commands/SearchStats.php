<?php

namespace App\Console\Commands;

use App\Services\SearchOrchestrationService;
use Illuminate\Console\Command;

class SearchStats extends Command
{
    protected $signature = 'search:stats {--health : Show health check} {--detailed : Show detailed statistics}';

    protected $description = 'Show search system statistics and status';

    public function handle(SearchOrchestrationService $searchOrchestration): int
    {
        $health = $this->option('health');
        $detailed = $this->option('detailed');

        $this->info('VerifySource Search System Statistics');
        $this->line('======================================');

        if ($health) {
            return $this->showHealthCheck($searchOrchestration);
        }

        // Get comprehensive statistics
        $stats = $searchOrchestration->getSearchStatistics();

        // System Status
        $this->info('System Status:');
        $systemStatus = $stats['system_status'];

        $meilisearchStatus = $systemStatus['meilisearch_available'] ? '✓ Available' : '✗ Unavailable';
        $qdrantStatus = $systemStatus['qdrant_available'] ? '✓ Available' : '✗ Unavailable';

        $this->line("  Meilisearch: {$meilisearchStatus}");
        $this->line("  Qdrant: {$qdrantStatus}");
        $this->line('  Default Engine: '.$systemStatus['default_engine']);
        $this->line('');

        // Content Statistics
        if (isset($stats['content_statistics']) && ! isset($stats['content_statistics']['error'])) {
            $contentStats = $stats['content_statistics'];
            $this->info('Content Statistics:');
            $this->line('  Total Articles: '.number_format($contentStats['total_articles']));
            $this->line('  Recent Articles (7 days): '.number_format($contentStats['recent_articles']));
            $this->line('  Total Sources: '.number_format($contentStats['total_sources']));
            $this->line('  Active Sources: '.number_format($contentStats['active_sources']));
            $this->line('  Daily Indexing Rate: '.$contentStats['indexing_rate'].' articles/day');
            $this->line('');
        }

        // Meilisearch Statistics
        if ($systemStatus['meilisearch_available'] && isset($stats['meilisearch'])) {
            $this->showMeilisearchStats($stats['meilisearch'], $detailed);
        }

        // Qdrant Statistics
        if ($systemStatus['qdrant_available'] && isset($stats['qdrant'])) {
            $this->showQdrantStats($stats['qdrant'], $detailed);
        }

        // Show recommendations if any services are unavailable
        if (! $systemStatus['meilisearch_available'] || ! $systemStatus['qdrant_available']) {
            $this->line('');
            $this->warn('Recommendations:');

            if (! $systemStatus['meilisearch_available']) {
                $this->line('• Start Meilisearch: docker run -it --rm -p 7700:7700 getmeili/meilisearch');
            }

            if (! $systemStatus['qdrant_available']) {
                $this->line('• Start Qdrant: docker run -p 6333:6333 qdrant/qdrant');
            }

            $this->line('• Check configuration in config/verifysource.php');
            $this->line('• Initialize system: php artisan search:init');
        }

        return self::SUCCESS;
    }

    protected function showHealthCheck(SearchOrchestrationService $searchOrchestration): int
    {
        $this->info('Search System Health Check');
        $this->line('===========================');

        $health = $searchOrchestration->healthCheck();

        // Overall status
        $statusColor = match ($health['overall_status']) {
            'healthy' => 'info',
            'degraded' => 'warn',
            'unhealthy' => 'error',
            default => 'line'
        };

        $this->$statusColor('Overall Status: '.strtoupper($health['overall_status']));
        $this->line('');

        // Service status
        $this->info('Service Status:');
        foreach ($health['services'] as $serviceName => $serviceHealth) {
            $status = $serviceHealth['status'];
            $statusSymbol = $status === 'healthy' ? '✓' : ($status === 'unavailable' ? '✗' : '?');

            $this->line("  {$statusSymbol} {$serviceName}: {$status}");

            if (isset($serviceHealth['version'])) {
                $this->line('    Version: '.$serviceHealth['version']);
            }

            if (isset($serviceHealth['indices'])) {
                $this->line('    Indices: '.$serviceHealth['indices']);
            }

            if (isset($serviceHealth['collections'])) {
                $this->line('    Collections: '.$serviceHealth['collections']);
            }
        }

        // Issues
        if (! empty($health['issues'])) {
            $this->line('');
            $this->error('Issues Found:');
            foreach ($health['issues'] as $issue) {
                $this->line("  • {$issue}");
            }
        }

        // Recommendations
        if (! empty($health['recommendations'])) {
            $this->line('');
            $this->warn('Recommendations:');
            foreach ($health['recommendations'] as $recommendation) {
                $this->line("  • {$recommendation}");
            }
        }

        return $health['overall_status'] === 'healthy' ? self::SUCCESS : self::FAILURE;
    }

    protected function showMeilisearchStats(array $meilisearchStats, bool $detailed): void
    {
        $this->info('Meilisearch Statistics:');

        if (isset($meilisearchStats['version'])) {
            $this->line('  Version: '.$meilisearchStats['version']);
        }

        if (isset($meilisearchStats['indices']) && ! empty($meilisearchStats['indices'])) {
            $this->line('  Indices:');

            foreach ($meilisearchStats['indices'] as $indexName => $indexInfo) {
                $docCount = number_format($indexInfo['number_of_documents']);
                $indexing = $indexInfo['is_indexing'] ? ' (indexing)' : '';

                $this->line("    • {$indexName}: {$docCount} documents{$indexing}");

                if ($detailed && isset($indexInfo['field_distribution'])) {
                    $this->line('      Fields: '.implode(', ', array_keys($indexInfo['field_distribution'])));
                }
            }
        }

        $this->line('');
    }

    protected function showQdrantStats(array $qdrantStats, bool $detailed): void
    {
        $this->info('Qdrant Statistics:');

        if (isset($qdrantStats['version'])) {
            $this->line('  Version: '.$qdrantStats['version']);
        }

        if (isset($qdrantStats['collections_info']) && ! empty($qdrantStats['collections_info'])) {
            $this->line('  Collections:');

            foreach ($qdrantStats['collections_info'] as $collectionName => $collectionInfo) {
                $pointsCount = isset($collectionInfo['points_count'])
                    ? number_format($collectionInfo['points_count'])
                    : 'Unknown';

                $this->line("    • {$collectionName}: {$pointsCount} vectors");

                if ($detailed) {
                    if (isset($collectionInfo['config']['params']['vectors']['size'])) {
                        $vectorSize = $collectionInfo['config']['params']['vectors']['size'];
                        $this->line("      Vector size: {$vectorSize}");
                    }

                    if (isset($collectionInfo['config']['params']['vectors']['distance'])) {
                        $distance = $collectionInfo['config']['params']['vectors']['distance'];
                        $this->line("      Distance metric: {$distance}");
                    }

                    if (isset($collectionInfo['status'])) {
                        $this->line('      Status: '.$collectionInfo['status']);
                    }
                }
            }
        }

        $this->line('');
    }
}
