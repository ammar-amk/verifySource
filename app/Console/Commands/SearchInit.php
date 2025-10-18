<?php

namespace App\Console\Commands;

use App\Services\SearchOrchestrationService;
use Illuminate\Console\Command;

class SearchInit extends Command
{
    protected $signature = 'search:init {--force : Force re-initialization of existing indices}';
    
    protected $description = 'Initialize search indices and collections for Meilisearch and Qdrant';

    public function handle(SearchOrchestrationService $searchOrchestration): int
    {
        $force = $this->option('force');
        
        $this->info('Initializing VerifySource search system...');
        
        if ($force) {
            $this->warn('Force mode enabled - existing indices will be recreated');
        }
        
        // Initialize the search system
        $result = $searchOrchestration->initializeSearchSystem();
        
        if (!$result['success']) {
            $this->error('✗ Failed to initialize search system');
            if (isset($result['error'])) {
                $this->error('Error: ' . $result['error']);
            }
            return self::FAILURE;
        }
        
        $this->info('✓ Search system initialization completed');
        $this->line("");
        
        // Show service status
        if ($result['meilisearch']['available'] ?? false) {
            $this->info('Meilisearch Status: Available');
            $meilisearch = $result['initialization']['meilisearch'] ?? [];
            foreach ($meilisearch as $indexName => $indexResult) {
                $status = $indexResult['success'] ? '✓' : '✗';
                $this->line("  {$status} Index: {$indexName}");
                
                if (isset($indexResult['stats'])) {
                    $docs = $indexResult['stats']['numberOfDocuments'] ?? 0;
                    $this->line("    Documents: {$docs}");
                }
            }
        } else {
            $this->warn('Meilisearch Status: Not available');
            if (isset($result['meilisearch']['error'])) {
                $this->line('  Error: ' . $result['meilisearch']['error']);
            }
        }
        
        $this->line("");
        
        if ($result['qdrant']['available'] ?? false) {
            $this->info('Qdrant Status: Available');
            $qdrant = $result['initialization']['qdrant'] ?? [];
            foreach ($qdrant as $collectionKey => $collectionResult) {
                $status = $collectionResult['success'] ? '✓' : '✗';
                $action = $collectionResult['action'] ?? 'unknown';
                $collectionName = $collectionResult['collection'] ?? $collectionKey;
                $this->line("  {$status} Collection: {$collectionName} ({$action})");
            }
        } else {
            $this->warn('Qdrant Status: Not available');
            if (isset($result['qdrant']['error'])) {
                $this->line('  Error: ' . $result['qdrant']['error']);
            }
        }
        
        $this->line("");
        
        // Show next steps
        $this->info('Next steps:');
        $this->line('1. Index existing content: php artisan search:index --all');
        $this->line('2. Check system status: php artisan search:stats');
        $this->line('3. Test search: php artisan search:query "your search term"');
        
        return self::SUCCESS;
    }
}