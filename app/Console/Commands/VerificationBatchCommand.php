<?php

namespace App\Console\Commands;

use App\Models\VerificationRequest;
use App\Models\VerificationResult;
use App\Services\VerificationResultService;
use App\Services\VerificationService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerificationBatchCommand extends Command
{
    protected $signature = 'verify:batch
                           {--pending : Process all pending verification requests}
                           {--request-ids= : Comma-separated list of specific request IDs to process}
                           {--limit=10 : Maximum number of requests to process}
                           {--timeout=300 : Timeout per verification in seconds}
                           {--parallel=1 : Number of parallel processes (not implemented yet)}
                           {--dry-run : Show what would be processed without actually processing}
                           {--verbose : Show detailed output}';

    protected $description = 'Process verification requests in batch mode';

    protected VerificationService $verificationService;

    protected VerificationResultService $resultService;

    public function __construct(
        VerificationService $verificationService,
        VerificationResultService $resultService
    ) {
        parent::__construct();
        $this->verificationService = $verificationService;
        $this->resultService = $resultService;
    }

    public function handle(): int
    {
        try {
            $pending = $this->option('pending');
            $requestIds = $this->option('request-ids');
            $limit = (int) $this->option('limit');
            $timeout = (int) $this->option('timeout');
            $dryRun = $this->option('dry-run');
            $verbose = $this->option('verbose');

            if (! $pending && ! $requestIds) {
                $this->error('Must specify either --pending or --request-ids');

                return Command::FAILURE;
            }

            // Get verification requests to process
            $requests = $this->getVerificationRequests($pending, $requestIds, $limit);

            if ($requests->isEmpty()) {
                $this->info('No verification requests found to process');

                return Command::SUCCESS;
            }

            $this->info("Found {$requests->count()} verification request(s) to process");

            if ($dryRun) {
                $this->info('DRY RUN - Would process the following requests:');
                $this->displayRequestsTable($requests);

                return Command::SUCCESS;
            }

            // Process requests
            $results = $this->processRequests($requests, $timeout, $verbose);

            // Display summary
            $this->displaySummary($results);

            // Determine exit code based on results
            $failures = collect($results)->where('success', false)->count();
            if ($failures > 0) {
                $this->warn("{$failures} verification(s) failed");

                return 1; // Partial failure
            }

            $this->info('All verifications completed successfully');

            return Command::SUCCESS;

        } catch (Exception $e) {
            Log::error('Batch verification command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('Batch verification failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function getVerificationRequests(bool $pending, ?string $requestIds, int $limit)
    {
        $query = VerificationRequest::query();

        if ($pending) {
            $query->where('status', 'pending')
                ->orderBy('created_at', 'asc');
        }

        if ($requestIds) {
            $ids = explode(',', $requestIds);
            $ids = array_map('trim', $ids);
            $ids = array_filter($ids, 'is_numeric');

            if (empty($ids)) {
                throw new Exception('Invalid request IDs provided');
            }

            $query->whereIn('id', $ids);
        }

        return $query->limit($limit)->get();
    }

    protected function processRequests($requests, int $timeout, bool $verbose): array
    {
        $results = [];
        $progressBar = $this->output->createProgressBar($requests->count());

        if (! $verbose) {
            $progressBar->start();
        }

        foreach ($requests as $request) {
            if ($verbose) {
                $this->info("Processing request #{$request->id}...");
            }

            $result = $this->processRequest($request, $timeout, $verbose);
            $results[] = $result;

            if (! $verbose) {
                $progressBar->advance();
            }
        }

        if (! $verbose) {
            $progressBar->finish();
            $this->newLine();
        }

        return $results;
    }

    protected function processRequest(VerificationRequest $request, int $timeout, bool $verbose): array
    {
        $startTime = microtime(true);
        $result = [
            'request_id' => $request->id,
            'success' => false,
            'error' => null,
            'processing_time' => 0,
            'confidence' => 0.0,
            'status' => 'failed',
        ];

        try {
            // Update request status
            $request->update(['status' => 'processing']);

            if ($verbose) {
                $this->line("  Content hash: {$request->content_hash}");
                $this->line("  Content type: {$request->content_type}");
            }

            // Set timeout
            set_time_limit($timeout);

            // Perform verification
            $verificationResult = $this->verificationService->verifyContent(
                $request->content,
                $request->metadata ?? [],
                $request->content_hash
            );

            // Store the results (this would create a VerificationResult record)
            // For now, we'll just update the request with the results
            $request->update([
                'status' => 'completed',
                'results' => $verificationResult,
                'completed_at' => now(),
            ]);

            $result['success'] = true;
            $result['confidence'] = $verificationResult['overall_confidence'] ?? 0.0;
            $result['status'] = $verificationResult['status'] ?? 'completed';

            if ($verbose) {
                $this->line('  ✓ Verification completed');
                $this->line("  Confidence: {$result['confidence']}");
                $this->line("  Status: {$result['status']}");
            }

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();

            // Update request with error
            $request->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            if ($verbose) {
                $this->error('  ✗ Verification failed: '.$e->getMessage());
            }

            Log::error('Batch verification request failed', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }

        $result['processing_time'] = round(microtime(true) - $startTime, 2);

        return $result;
    }

    protected function displayRequestsTable($requests): void
    {
        $tableData = [['ID', 'Content Hash', 'Type', 'Created', 'Status']];

        foreach ($requests as $request) {
            $tableData[] = [
                $request->id,
                substr($request->content_hash, 0, 16).'...',
                $request->content_type ?? 'unknown',
                $request->created_at->format('Y-m-d H:i'),
                $request->status,
            ];
        }

        $this->table($tableData[0], array_slice($tableData, 1));
    }

    protected function displaySummary(array $results): void
    {
        $this->newLine();
        $this->info('=== BATCH VERIFICATION SUMMARY ===');

        $totalRequests = count($results);
        $successful = collect($results)->where('success', true)->count();
        $failed = $totalRequests - $successful;

        $this->line("Total requests processed: {$totalRequests}");
        $this->line("Successful verifications: <fg=green>{$successful}</>");
        $this->line("Failed verifications: <fg=red>{$failed}</>");

        if ($successful > 0) {
            $avgProcessingTime = collect($results)
                ->where('success', true)
                ->avg('processing_time');

            $avgConfidence = collect($results)
                ->where('success', true)
                ->avg('confidence');

            $this->line('Average processing time: '.round($avgProcessingTime, 2).'s');
            $this->line('Average confidence: '.round($avgConfidence, 3));
        }

        // Show confidence distribution
        if ($successful > 0) {
            $this->newLine();
            $this->info('Confidence Distribution:');

            $confidenceRanges = [
                'Very High (≥0.8)' => 0,
                'High (0.6-0.8)' => 0,
                'Medium (0.4-0.6)' => 0,
                'Low (0.2-0.4)' => 0,
                'Very Low (<0.2)' => 0,
            ];

            foreach ($results as $result) {
                if (! $result['success']) {
                    continue;
                }

                $confidence = $result['confidence'];

                if ($confidence >= 0.8) {
                    $confidenceRanges['Very High (≥0.8)']++;
                } elseif ($confidence >= 0.6) {
                    $confidenceRanges['High (0.6-0.8)']++;
                } elseif ($confidence >= 0.4) {
                    $confidenceRanges['Medium (0.4-0.6)']++;
                } elseif ($confidence >= 0.2) {
                    $confidenceRanges['Low (0.2-0.4)']++;
                } else {
                    $confidenceRanges['Very Low (<0.2)']++;
                }
            }

            foreach ($confidenceRanges as $range => $count) {
                $this->line("  {$range}: {$count}");
            }
        }

        // Show failures if any
        if ($failed > 0) {
            $this->newLine();
            $this->error('Failed Verifications:');

            $failures = collect($results)->where('success', false);
            foreach ($failures as $failure) {
                $this->line("  Request #{$failure['request_id']}: {$failure['error']}");
            }
        }
    }
}
