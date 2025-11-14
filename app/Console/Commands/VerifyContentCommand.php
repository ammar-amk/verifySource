<?php

namespace App\Console\Commands;

use App\Services\ContentHashService;
use App\Services\VerificationService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class VerifyContentCommand extends Command
{
    protected $signature = 'verify:content
                           {content : The content to verify}
                           {--url= : Optional URL associated with the content}
                           {--author= : Optional author information}
                           {--published-at= : Optional publication date}
                           {--format=json : Output format (json, table, summary)}
                           {--save : Save results to database}
                           {--verbose : Show detailed output}';

    protected $description = 'Verify content using the comprehensive verification engine';

    protected VerificationService $verificationService;

    protected ContentHashService $contentHashService;

    public function __construct(
        VerificationService $verificationService,
        ContentHashService $contentHashService
    ) {
        parent::__construct();
        $this->verificationService = $verificationService;
        $this->contentHashService = $contentHashService;
    }

    public function handle(): int
    {
        try {
            $content = $this->argument('content');
            $url = $this->option('url');
            $author = $this->option('author');
            $publishedAt = $this->option('published-at');
            $format = $this->option('format');
            $save = $this->option('save');
            $verbose = $this->option('verbose');

            $this->info('Starting content verification...');

            if ($verbose) {
                $this->line('Content length: '.strlen($content).' characters');
                if ($url) {
                    $this->line("URL: {$url}");
                }
                if ($author) {
                    $this->line("Author: {$author}");
                }
                if ($publishedAt) {
                    $this->line("Published: {$publishedAt}");
                }
            }

            // Create content metadata
            $metadata = array_filter([
                'url' => $url,
                'author' => $author,
                'published_at' => $publishedAt,
                'source' => 'cli_verification',
            ]);

            // Generate content hash
            $contentHash = $this->contentHashService->generateHash($content, $metadata);

            if ($verbose) {
                $this->line("Content hash: {$contentHash}");
            }

            // Perform verification
            $this->info('Performing comprehensive verification...');

            $verificationResult = $this->verificationService->verifyContent(
                $content,
                $metadata,
                $contentHash
            );

            // Handle save option
            if ($save) {
                // For CLI usage, we'll need to create a verification request
                // This would typically be done through the web interface
                $this->warn('Save option not implemented for CLI - use web interface for persistent storage');
            }

            // Output results based on format
            $this->outputResults($verificationResult, $format, $verbose);

            // Determine exit code based on verification confidence
            $confidence = $verificationResult['overall_confidence'] ?? 0.0;
            if ($confidence >= 0.7) {
                $this->info('Verification completed with high confidence');

                return Command::SUCCESS;
            } elseif ($confidence >= 0.4) {
                $this->warn('Verification completed with moderate confidence');

                return Command::SUCCESS;
            } else {
                $this->error('Verification completed with low confidence - manual review recommended');

                return 1; // Non-zero exit code for low confidence
            }

        } catch (Exception $e) {
            Log::error('Content verification command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error('Verification failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function outputResults(array $result, string $format, bool $verbose): void
    {
        switch ($format) {
            case 'json':
                $this->line(json_encode($result, JSON_PRETTY_PRINT));
                break;

            case 'table':
                $this->outputTableFormat($result, $verbose);
                break;

            case 'summary':
            default:
                $this->outputSummaryFormat($result, $verbose);
                break;
        }
    }

    protected function outputSummaryFormat(array $result, bool $verbose): void
    {
        $this->newLine();
        $this->info('=== VERIFICATION SUMMARY ===');

        // Overall results
        $confidence = $result['overall_confidence'] ?? 0.0;
        $status = $result['status'] ?? 'unknown';

        $confidenceLabel = $this->getConfidenceLabel($confidence);
        $this->line("Overall Confidence: <fg={$this->getConfidenceColor($confidence)}>{$confidenceLabel} ({$confidence})</>");
        $this->line("Verification Status: <fg={$this->getStatusColor($status)}>{$status}</>");

        // Key findings
        if (! empty($result['findings'])) {
            $this->newLine();
            $this->info('Key Findings:');
            foreach (array_slice($result['findings'], 0, 5) as $finding) {
                $type = $finding['type'] ?? 'unknown';
                $description = $finding['description'] ?? 'No description';
                $confidence = $finding['confidence'] ?? 0.0;

                $this->line("  • {$description} (confidence: {$confidence})");
            }
        }

        // Recommendations
        if (! empty($result['recommendations'])) {
            $this->newLine();
            $this->info('Recommendations:');
            foreach ($result['recommendations'] as $recommendation) {
                $this->line("  • {$recommendation}");
            }
        }

        // Evidence summary (if verbose)
        if ($verbose && ! empty($result['evidence_summary'])) {
            $this->newLine();
            $this->info('Evidence Summary:');
            $this->line('  '.$result['evidence_summary']);
        }

        // Detailed analysis (if verbose)
        if ($verbose) {
            $this->outputDetailedAnalysis($result);
        }
    }

    protected function outputTableFormat(array $result, bool $verbose): void
    {
        $this->newLine();
        $this->info('=== VERIFICATION RESULTS ===');

        // Main results table
        $mainData = [
            ['Metric', 'Value'],
            ['Overall Confidence', ($result['overall_confidence'] ?? 0.0)],
            ['Verification Status', $result['status'] ?? 'unknown'],
            ['Findings Count', count($result['findings'] ?? [])],
            ['Evidence Sources', count($result['evidence'] ?? [])],
            ['Processing Time', $result['processing_time'] ?? 'N/A'],
        ];

        $this->table($mainData[0], array_slice($mainData, 1));

        // Findings table
        if (! empty($result['findings'])) {
            $this->newLine();
            $this->info('Findings:');

            $findingsData = [['Type', 'Description', 'Confidence']];
            foreach ($result['findings'] as $finding) {
                $findingsData[] = [
                    $finding['type'] ?? 'unknown',
                    str_limit($finding['description'] ?? 'No description', 50),
                    $finding['confidence'] ?? 0.0,
                ];
            }

            $this->table($findingsData[0], array_slice($findingsData, 1));
        }

        if ($verbose) {
            $this->outputDetailedAnalysis($result);
        }
    }

    protected function outputDetailedAnalysis(array $result): void
    {
        $this->newLine();
        $this->info('=== DETAILED ANALYSIS ===');

        // Search analysis
        if (isset($result['search_analysis'])) {
            $search = $result['search_analysis'];
            $this->info('Search Analysis:');
            $this->line('  Matches found: '.($search['total_matches'] ?? 0));
            $this->line('  Best match score: '.($search['best_match_score'] ?? 0.0));
            $this->line('  Match confidence: '.($search['match_confidence'] ?? 0.0));
        }

        // Provenance analysis
        if (isset($result['provenance_analysis'])) {
            $provenance = $result['provenance_analysis'];
            $this->info('Provenance Analysis:');
            $this->line('  Confidence: '.($provenance['confidence'] ?? 0.0));
            if (isset($provenance['original_source'])) {
                $original = $provenance['original_source'];
                $this->line('  Original source: '.($original['source_name'] ?? 'Unknown'));
                $this->line('  Publication timeline: '.count($provenance['publication_timeline'] ?? []));
            }
        }

        // Credibility analysis
        if (isset($result['credibility_analysis'])) {
            $credibility = $result['credibility_analysis'];
            $this->info('Credibility Analysis:');
            $this->line('  Overall score: '.($credibility['overall_score'] ?? 0.0));
            $this->line('  Assessment confidence: '.($credibility['confidence'] ?? 0.0));

            if (! empty($credibility['warnings'])) {
                $this->line('  Warnings: '.count($credibility['warnings']));
            }
        }

        // Wayback analysis
        if (isset($result['wayback_analysis'])) {
            $wayback = $result['wayback_analysis'];
            $this->info('Wayback Machine Analysis:');
            $this->line('  Snapshots found: '.($wayback['total_snapshots'] ?? 0));
            $this->line('  Earliest capture: '.($wayback['first_capture'] ?? 'None'));
            $this->line('  Verification confidence: '.($wayback['verification_confidence'] ?? 0.0));
        }
    }

    protected function getConfidenceLabel(float $confidence): string
    {
        if ($confidence >= 0.8) {
            return 'Very High';
        }
        if ($confidence >= 0.6) {
            return 'High';
        }
        if ($confidence >= 0.4) {
            return 'Medium';
        }
        if ($confidence >= 0.2) {
            return 'Low';
        }

        return 'Very Low';
    }

    protected function getConfidenceColor(float $confidence): string
    {
        if ($confidence >= 0.7) {
            return 'green';
        }
        if ($confidence >= 0.4) {
            return 'yellow';
        }

        return 'red';
    }

    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'verified' => 'green',
            'suspicious' => 'red',
            'unverifiable' => 'yellow',
            default => 'white'
        };
    }
}
