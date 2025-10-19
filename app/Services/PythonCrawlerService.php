<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Exception;

class PythonCrawlerService
{
    protected string $pythonPath;
    protected string $crawlerScriptPath;
    protected string $jobProcessorPath;

    public function __construct()
    {
        $this->pythonPath = config('verifysource.python.executable', base_path('venv/Scripts/python.exe'));
        $this->crawlerScriptPath = base_path('crawlers/standalone_url_crawler.py');
        $this->jobProcessorPath = base_path('crawlers/process_jobs.py');
    }

    public function crawlUrl(string $url, int $sourceId = null, int $crawlJobId = null): array
    {
        $command = [
            $this->pythonPath,
            $this->crawlerScriptPath,
            '--url', $url,
        ];

        if ($sourceId) {
            $command[] = '--source-id';
            $command[] = (string) $sourceId;
        }

        if ($crawlJobId) {
            $command[] = '--crawl-job-id';
            $command[] = (string) $crawlJobId;
        }

        $command[] = '--output';
        $outputFile = storage_path('app/temp/crawl_result_' . time() . '.json');
        $command[] = $outputFile;

        Log::info("Executing Python crawler", [
            'command' => implode(' ', $command),
            'url' => $url,
            'source_id' => $sourceId,
            'crawl_job_id' => $crawlJobId
        ]);

        try {
            $result = Process::run($command);

            if ($result->successful()) {
                // Read the output file
                if (file_exists($outputFile)) {
                    $output = json_decode(file_get_contents($outputFile), true);
                    unlink($outputFile); // Clean up temp file
                    
                    Log::info("Python crawler completed successfully", [
                        'url' => $url,
                        'title' => $output['title'] ?? 'No title'
                    ]);

                    return [
                        'success' => true,
                        'data' => $output,
                        'extraction_method' => 'python'
                    ];
                } else {
                    return [
                        'success' => true,
                        'data' => null,
                        'message' => 'Crawler completed but no output file generated'
                    ];
                }
            } else {
                throw new Exception("Python crawler failed: " . $result->errorOutput());
            }

        } catch (Exception $e) {
            Log::error("Python crawler error", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    public function crawlSource(int $sourceId, string $sourceUrl, string $sourceType = 'news', int $maxPages = 100): array
    {
        $command = [
            $this->pythonPath,
            $this->crawlerScriptPath,
            '--source-id', (string) $sourceId,
            '--source-url', $sourceUrl,
            '--source-type', $sourceType,
            '--max-pages', (string) $maxPages,
        ];

        Log::info("Executing Python source crawler", [
            'command' => implode(' ', $command),
            'source_id' => $sourceId,
            'source_url' => $sourceUrl
        ]);

        try {
            $result = Process::run($command);

            if ($result->successful()) {
                Log::info("Python source crawler completed successfully", [
                    'source_id' => $sourceId,
                    'source_url' => $sourceUrl
                ]);

                return [
                    'success' => true,
                    'message' => 'Source crawl initiated successfully'
                ];
            } else {
                throw new Exception("Python source crawler failed: " . $result->errorOutput());
            }

        } catch (Exception $e) {
            Log::error("Python source crawler error", [
                'source_id' => $sourceId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function crawlSitemap(string $sitemapUrl, int $sourceId): array
    {
        $command = [
            $this->pythonPath,
            $this->crawlerScriptPath,
            '--sitemap', $sitemapUrl,
            '--source-id', (string) $sourceId,
        ];

        Log::info("Executing Python sitemap crawler", [
            'command' => implode(' ', $command),
            'sitemap_url' => $sitemapUrl,
            'source_id' => $sourceId
        ]);

        try {
            $result = Process::run($command);

            if ($result->successful()) {
                Log::info("Python sitemap crawler completed successfully", [
                    'sitemap_url' => $sitemapUrl,
                    'source_id' => $sourceId
                ]);

                return [
                    'success' => true,
                    'message' => 'Sitemap crawl completed successfully'
                ];
            } else {
                throw new Exception("Python sitemap crawler failed: " . $result->errorOutput());
            }

        } catch (Exception $e) {
            Log::error("Python sitemap crawler error", [
                'sitemap_url' => $sitemapUrl,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function processPendingJobs(int $maxJobs = 10): array
    {
        $command = [
            $this->pythonPath,
            $this->jobProcessorPath,
            '--max-jobs', (string) $maxJobs,
            '--verbose'
        ];

        Log::info("Executing Python job processor", [
            'command' => implode(' ', $command),
            'max_jobs' => $maxJobs
        ]);

        try {
            $result = Process::timeout(300)->run($command); // 5-minute timeout

            if ($result->successful()) {
                Log::info("Python job processor completed successfully", [
                    'max_jobs' => $maxJobs,
                    'output' => $result->output()
                ]);

                return [
                    'success' => true,
                    'output' => $result->output(),
                    'jobs_processed' => $this->extractJobsProcessedCount($result->output())
                ];
            } else {
                throw new Exception("Python job processor failed: " . $result->errorOutput());
            }

        } catch (Exception $e) {
            Log::error("Python job processor error", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function startContinuousProcessor(): array
    {
        $command = [
            $this->pythonPath,
            $this->jobProcessorPath,
            '--continuous',
            '--sleep-interval', '30',
            '--verbose'
        ];

        Log::info("Starting Python continuous job processor", [
            'command' => implode(' ', $command)
        ]);

        try {
            // Start the process in the background
            $result = Process::start($command);

            Log::info("Python continuous job processor started", [
                'pid' => $result->id() ?? 'unknown'
            ]);

            return [
                'success' => true,
                'message' => 'Continuous job processor started',
                'process_id' => $result->id()
            ];

        } catch (Exception $e) {
            Log::error("Failed to start continuous job processor", [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function checkPythonEnvironment(): array
    {
        $checks = [];

        // Check if Python is available
        try {
            $result = Process::run([$this->pythonPath, '--version']);
            $checks['python'] = [
                'available' => $result->successful(),
                'version' => trim($result->output()),
                'path' => $this->pythonPath
            ];
        } catch (Exception $e) {
            $checks['python'] = [
                'available' => false,
                'error' => $e->getMessage()
            ];
        }

        // Check if crawler script exists
        $checks['crawler_script'] = [
            'exists' => file_exists($this->crawlerScriptPath),
            'path' => $this->crawlerScriptPath
        ];

        // Check if job processor script exists
        $checks['job_processor'] = [
            'exists' => file_exists($this->jobProcessorPath),
            'path' => $this->jobProcessorPath
        ];

        // Check required Python packages
        $requiredPackages = [
            'scrapy' => 'scrapy',
            'newspaper3k' => 'newspaper', 
            'mysql-connector-python' => 'mysql.connector'
        ];
        
        foreach ($requiredPackages as $packageName => $importName) {
            try {
                $result = Process::run([$this->pythonPath, '-c', "import $importName; print('OK')"]);
                $checks["package_$packageName"] = [
                    'installed' => $result->successful(),
                    'output' => trim($result->output())
                ];
            } catch (Exception $e) {
                $checks["package_$packageName"] = [
                    'installed' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        // Overall status
        $allGood = $checks['python']['available'] && 
                   $checks['crawler_script']['exists'] && 
                   $checks['job_processor']['exists'];

        foreach ($requiredPackages as $packageName => $importName) {
            $allGood = $allGood && $checks["package_$packageName"]['installed'];
        }

        return [
            'ready' => $allGood,
            'checks' => $checks
        ];
    }

    protected function extractJobsProcessedCount(string $output): int
    {
        // Extract number of processed jobs from output
        if (preg_match('/Processed (\d+) crawl jobs/', $output, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    public function isPythonAvailable(): bool
    {
        try {
            $result = Process::run([$this->pythonPath, '--version']);
            return $result->successful();
        } catch (Exception $e) {
            return false;
        }
    }
}