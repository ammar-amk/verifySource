<?php

namespace App\Console\Commands;

use App\Services\ContentMatchingService;
use App\Services\SearchOrchestrationService;
use Illuminate\Console\Command;

class SearchQuery extends Command
{
    protected $signature = 'search:query {query : Search query} {--engine=hybrid : Search engine to use (meilisearch, qdrant, hybrid)} {--limit=10 : Maximum number of results} {--threshold=0.7 : Similarity threshold for semantic search} {--show-scores : Show match scores} {--find-source : Find original source of content}';

    protected $description = 'Test search functionality with a query';

    public function handle(
        SearchOrchestrationService $searchOrchestration,
        ContentMatchingService $contentMatching
    ): int {
        $query = $this->argument('query');
        $engine = $this->option('engine');
        $limit = intval($this->option('limit'));
        $threshold = floatval($this->option('threshold'));
        $showScores = $this->option('show-scores');
        $findSource = $this->option('find-source');

        $this->info("Searching for: \"{$query}\"");
        $this->line("Engine: {$engine} | Limit: {$limit} | Threshold: {$threshold}");
        $this->line('');

        // Perform search based on mode
        if ($findSource) {
            return $this->findContentSource($query, $contentMatching);
        } else {
            return $this->performSearch($query, $searchOrchestration, $engine, $limit, $threshold, $showScores);
        }
    }

    protected function performSearch(
        string $query,
        SearchOrchestrationService $searchOrchestration,
        string $engine,
        int $limit,
        float $threshold,
        bool $showScores
    ): int {
        $options = [
            'limit' => $limit,
            'score_threshold' => $threshold,
        ];

        // Override default engine if specified
        if ($engine !== 'hybrid') {
            $config = config('verifysource.search');
            $originalEngine = $config['default_engine'];
            config(['verifysource.search.default_engine' => $engine]);
        }

        $result = $searchOrchestration->searchContent($query, $options);

        // Restore original engine
        if (isset($originalEngine)) {
            config(['verifysource.search.default_engine' => $originalEngine]);
        }

        if (! empty($result['error'])) {
            $this->error('Search failed: '.$result['error']);

            return self::FAILURE;
        }

        $matches = $result['matches'] ?? [];

        if (empty($matches)) {
            $this->warn('No matches found');

            return self::SUCCESS;
        }

        $this->info("Found {$result['duplicate_likelihood']} matches (Processing time: {$result['processing_time']}ms)");
        $this->line('Duplicate likelihood: '.round($result['duplicate_likelihood'] * 100, 1).'%');
        $this->line('');

        // Show service-specific results if hybrid
        if ($engine === 'hybrid') {
            if (isset($result['meilisearch'])) {
                $mCount = count($result['meilisearch']['results'] ?? []);
                $mTime = $result['meilisearch']['processing_time'] ?? 'N/A';
                $this->line("Meilisearch: {$mCount} results ({$mTime}ms)");
            }

            if (isset($result['qdrant'])) {
                $qCount = count($result['qdrant']['results'] ?? []);
                $this->line("Qdrant: {$qCount} results");
            }

            $this->line('');
        }

        // Display results
        foreach ($matches as $index => $match) {
            $this->displayMatch($match, $index + 1, $showScores);
        }

        return self::SUCCESS;
    }

    protected function findContentSource(string $content, ContentMatchingService $contentMatching): int
    {
        $this->info('Finding original source of content...');
        $this->line('');

        $result = $contentMatching->findContentSource($content);

        if (! $result['found_source']) {
            $this->warn('No original source found');
            $this->line('Confidence: '.round($result['confidence'] * 100, 1).'%');

            return self::SUCCESS;
        }

        $this->info('✓ Original source found!');
        $this->line('Confidence: '.round($result['confidence'] * 100, 1).'%');
        $this->line('');

        $original = $result['original_article'];

        $this->line('Original Article:');
        $this->line('  Title: '.($original['title'] ?? 'N/A'));
        $this->line('  URL: '.($original['url'] ?? 'N/A'));
        $this->line('  Source: '.($original['source_name'] ?? 'N/A'));
        $this->line('  Published: '.($original['published_at'] ? date('Y-m-d H:i:s', $original['published_at']) : 'N/A'));
        $this->line('  Quality Score: '.($original['quality_score'] ?? 'N/A'));

        $this->line('');
        $this->line('Source Statistics:');
        $this->line('  Source ID: '.$result['source_id']);
        $this->line('  Total matches from source: '.$result['total_matches_from_source']);

        if ($this->option('verbose')) {
            $this->line('');
            $this->line('All matches from source:');
            foreach ($result['all_matches'] as $index => $match) {
                if ($match['source_id'] == $result['source_id']) {
                    $this->displayMatch($match, $index + 1, true);
                }
            }
        }

        return self::SUCCESS;
    }

    protected function displayMatch(array $match, int $position, bool $showScores): void
    {
        $this->line("{$position}. ".($match['title'] ?? 'No title'));
        $this->line('   URL: '.($match['url'] ?? 'N/A'));
        $this->line('   Source: '.($match['source_name'] ?? 'Unknown'));

        if (isset($match['published_at'])) {
            $publishedAt = is_numeric($match['published_at'])
                ? date('Y-m-d H:i:s', $match['published_at'])
                : $match['published_at'];
            $this->line("   Published: {$publishedAt}");
        }

        if (isset($match['quality_score'])) {
            $this->line('   Quality: '.$match['quality_score']);
        }

        if ($showScores) {
            if (isset($match['hybrid_score'])) {
                $this->line('   Hybrid Score: '.round($match['hybrid_score'], 3));
                $this->line('   Text Score: '.round($match['meilisearch_score'] ?? 0, 3));
                $this->line('   Semantic Score: '.round($match['qdrant_score'] ?? 0, 3));
            } elseif (isset($match['score'])) {
                $this->line('   Score: '.round($match['score'], 3));
            }

            if (isset($match['match_type'])) {
                $this->line('   Match Type: '.$match['match_type']);
            }
        }

        if (isset($match['excerpt']) && ! empty($match['excerpt'])) {
            $this->line('   Excerpt: '.substr($match['excerpt'], 0, 100).'...');
        }

        // Show highlighted content if available
        if (isset($match['highlighted']['title']) && $match['highlighted']['title'] !== $match['title']) {
            $this->line('   Highlighted: '.strip_tags($match['highlighted']['title']));
        }

        $this->line('');
    }
}
