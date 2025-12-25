<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\PythonCrawlerService;
use Illuminate\Console\Command;

class PythonCrawlUrl extends Command
{
    protected $signature = 'crawl:python:url {url : URL to crawl} {--source-id= : Source ID} {--show-data : Show extracted data}';

    protected $description = 'Crawl a single URL using Python crawler';

    public function handle(PythonCrawlerService $pythonCrawler): int
    {
        $url = $this->argument('url');
        $sourceId = $this->option('source-id');
        $showData = $this->option('show-data');

        // Validate URL
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error("Invalid URL: {$url}");

            return self::FAILURE;
        }

        // Validate source ID if provided
        if ($sourceId) {
            $source = Source::find($sourceId);
            if (! $source) {
                $this->error("Source not found: {$sourceId}");

                return self::FAILURE;
            }

            $this->info("Using source: {$source->name} ({$source->domain})");
        }

        $this->info("Crawling URL with Python: {$url}");

        // Check Python environment
        $envCheck = $pythonCrawler->checkPythonEnvironment();
        if (! $envCheck['ready']) {
            $this->error("Python environment is not ready. Run 'php artisan crawl:python:check' for details.");

            return self::FAILURE;
        }

        // Crawl the URL
        $result = $pythonCrawler->crawlUrl($url, $sourceId);

        if ($result['success']) {
            $data = $result['data'];

            if ($data) {
                $this->info('✓ URL crawled successfully!');
                $this->line('');
                $this->line('Title: '.($data['title'] ?? 'N/A'));
                $this->line('Author: '.($data['authors'] ?? 'N/A'));
                $this->line('Published: '.($data['published_at'] ?? 'N/A'));
                $this->line('Language: '.($data['language'] ?? 'N/A'));
                $this->line('Content length: '.strlen($data['content'] ?? ''));
                $this->line('Word count: '.($data['word_count'] ?? 'N/A'));
                $this->line('Quality score: '.($data['quality_score'] ?? 'N/A'));

                if (! empty($data['keywords'])) {
                    $this->line('Keywords: '.implode(', ', array_slice($data['keywords'], 0, 5)));
                }

                if (! empty($data['top_image'])) {
                    $this->line('Top image: '.$data['top_image']);
                }

                if ($showData) {
                    $this->line('');
                    $this->info('=== Extracted Data ===');

                    if (! empty($data['excerpt'])) {
                        $this->line('Excerpt:');
                        $this->line($data['excerpt']);
                    }

                    if (! empty($data['summary'])) {
                        $this->line('');
                        $this->line('AI Summary:');
                        $this->line($data['summary']);
                    }

                    if (! empty($data['content'])) {
                        $this->line('');
                        $this->line('Content preview:');
                        $this->line(substr($data['content'], 0, 500).'...');
                    }

                    if (! empty($data['quality_factors'])) {
                        $this->line('');
                        $this->line('Quality factors:');
                        foreach ($data['quality_factors'] as $factor) {
                            $this->line("  + {$factor}");
                        }
                    }

                    if (! empty($data['quality_issues'])) {
                        $this->line('');
                        $this->line('Quality issues:');
                        foreach ($data['quality_issues'] as $issue) {
                            $this->line("  - {$issue}");
                        }
                    }
                }

            } else {
                $this->warn('URL crawled but no data was extracted');
            }

        } else {
            $this->error('✗ Crawl failed: '.$result['error']);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
