<?php

namespace App\Console\Commands;

use App\Services\VerificationResultService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;

class VerificationReportCommand extends Command
{
    protected $signature = 'verify:report
                           {--type=summary : Report type (summary, detailed, trends, export)}
                           {--from= : Start date (YYYY-MM-DD)}
                           {--to= : End date (YYYY-MM-DD)}
                           {--status= : Filter by verification status}
                           {--confidence-min= : Minimum confidence threshold}
                           {--confidence-max= : Maximum confidence threshold}
                           {--format=table : Output format (table, json, csv)}
                           {--output= : Output file path}
                           {--limit=100 : Maximum number of results}';

    protected $description = 'Generate verification reports and statistics';

    protected VerificationResultService $resultService;

    public function __construct(VerificationResultService $resultService)
    {
        parent::__construct();
        $this->resultService = $resultService;
    }

    public function handle(): int
    {
        try {
            $type = $this->option('type');
            $from = $this->option('from');
            $to = $this->option('to');
            $status = $this->option('status');
            $confidenceMin = $this->option('confidence-min');
            $confidenceMax = $this->option('confidence-max');
            $format = $this->option('format');
            $outputFile = $this->option('output');
            $limit = (int) $this->option('limit');

            // Build filters
            $filters = [];

            if ($from) {
                $filters['date_from'] = Carbon::parse($from);
            }

            if ($to) {
                $filters['date_to'] = Carbon::parse($to)->endOfDay();
            }

            if ($status) {
                $filters['status'] = $status;
            }

            if ($confidenceMin !== null) {
                $filters['confidence_min'] = (float) $confidenceMin;
            }

            if ($confidenceMax !== null) {
                $filters['confidence_max'] = (float) $confidenceMax;
            }

            // Generate report based on type
            switch ($type) {
                case 'summary':
                    return $this->generateSummaryReport($filters, $format, $outputFile);

                case 'detailed':
                    return $this->generateDetailedReport($filters, $format, $outputFile, $limit);

                case 'trends':
                    return $this->generateTrendsReport($filters, $format, $outputFile);

                case 'export':
                    return $this->generateExportReport($filters, $format, $outputFile, $limit);

                default:
                    $this->error("Unknown report type: {$type}");

                    return Command::FAILURE;
            }

        } catch (Exception $e) {
            $this->error('Report generation failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function generateSummaryReport(array $filters, string $format, ?string $outputFile): int
    {
        $this->info('Generating verification summary report...');

        $statistics = $this->resultService->getVerificationStatistics($filters);

        switch ($format) {
            case 'json':
                $output = json_encode($statistics, JSON_PRETTY_PRINT);
                break;

            case 'csv':
                $output = $this->convertStatisticsToCSV($statistics);
                break;

            case 'table':
            default:
                $this->displaySummaryTable($statistics);

                return Command::SUCCESS;
        }

        if ($outputFile) {
            file_put_contents($outputFile, $output);
            $this->info("Report saved to: {$outputFile}");
        } else {
            $this->line($output);
        }

        return Command::SUCCESS;
    }

    protected function generateDetailedReport(array $filters, string $format, ?string $outputFile, int $limit): int
    {
        $this->info('Generating detailed verification report...');

        $criteria = array_merge($filters, ['limit' => $limit]);
        $results = $this->resultService->searchResults($criteria);

        switch ($format) {
            case 'json':
                $output = json_encode($results->toArray(), JSON_PRETTY_PRINT);
                break;

            case 'csv':
                $output = $this->convertResultsToCSV($results);
                break;

            case 'table':
            default:
                $this->displayDetailedTable($results);

                return Command::SUCCESS;
        }

        if ($outputFile) {
            file_put_contents($outputFile, $output);
            $this->info("Report saved to: {$outputFile}");
        } else {
            $this->line($output);
        }

        return Command::SUCCESS;
    }

    protected function generateTrendsReport(array $filters, string $format, ?string $outputFile): int
    {
        $this->info('Generating verification trends report...');

        $statistics = $this->resultService->getVerificationStatistics($filters);
        $trends = $statistics['temporal_trends'] ?? [];

        switch ($format) {
            case 'json':
                $output = json_encode($trends, JSON_PRETTY_PRINT);
                break;

            case 'csv':
                $output = $this->convertTrendsToCSV($trends);
                break;

            case 'table':
            default:
                $this->displayTrendsTable($trends);

                return Command::SUCCESS;
        }

        if ($outputFile) {
            file_put_contents($outputFile, $output);
            $this->info("Report saved to: {$outputFile}");
        } else {
            $this->line($output);
        }

        return Command::SUCCESS;
    }

    protected function generateExportReport(array $filters, string $format, ?string $outputFile, int $limit): int
    {
        $this->info('Generating verification export report...');

        // Get all results for export
        $criteria = array_merge($filters, ['limit' => $limit]);
        $results = $this->resultService->searchResults($criteria);

        $exportData = [];
        foreach ($results as $result) {
            $analysis = $this->resultService->getResultWithAnalysis($result->id);
            $exportData[] = $analysis;
        }

        switch ($format) {
            case 'json':
                $output = json_encode($exportData, JSON_PRETTY_PRINT);
                break;

            case 'csv':
                $output = $this->convertExportToCSV($exportData);
                break;

            default:
                $this->error('Export format must be json or csv');

                return Command::FAILURE;
        }

        if ($outputFile) {
            file_put_contents($outputFile, $output);
            $this->info("Export saved to: {$outputFile}");
        } else {
            $this->line($output);
        }

        return Command::SUCCESS;
    }

    protected function displaySummaryTable(array $statistics): void
    {
        $this->newLine();
        $this->info('=== VERIFICATION STATISTICS SUMMARY ===');

        // Main statistics
        $mainData = [
            ['Metric', 'Value'],
            ['Total Verifications', $statistics['total_verifications']],
            ['Average Confidence', round($statistics['average_confidence'], 3)],
            ['Completion Rate', round($statistics['completion_rate'] * 100, 1).'%'],
        ];

        $this->table($mainData[0], array_slice($mainData, 1));

        // Status breakdown
        if (! empty($statistics['status_breakdown'])) {
            $this->newLine();
            $this->info('Status Breakdown:');

            $statusData = [['Status', 'Count', 'Percentage']];
            $total = $statistics['total_verifications'];

            foreach ($statistics['status_breakdown'] as $status => $count) {
                $percentage = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                $statusData[] = [$status, $count, $percentage.'%'];
            }

            $this->table($statusData[0], array_slice($statusData, 1));
        }

        // Confidence distribution
        if (! empty($statistics['confidence_distribution'])) {
            $this->newLine();
            $this->info('Confidence Distribution:');

            $confData = [['Range', 'Count']];
            foreach ($statistics['confidence_distribution'] as $range => $count) {
                $confData[] = [ucfirst(str_replace('_', ' ', $range)), $count];
            }

            $this->table($confData[0], array_slice($confData, 1));
        }

        // Top findings
        if (! empty($statistics['top_findings'])) {
            $this->newLine();
            $this->info('Top Findings:');

            $findingsData = [['Finding Type', 'Occurrences']];
            foreach (array_slice($statistics['top_findings'], 0, 10) as $finding => $count) {
                $findingsData[] = [$finding, $count];
            }

            $this->table($findingsData[0], array_slice($findingsData, 1));
        }
    }

    protected function displayDetailedTable($results): void
    {
        $this->newLine();
        $this->info('=== DETAILED VERIFICATION RESULTS ===');
        $this->line("Showing {$results->count()} results");

        $tableData = [['ID', 'Hash', 'Confidence', 'Status', 'Verified At', 'Findings']];

        foreach ($results as $result) {
            $tableData[] = [
                $result->id,
                substr($result->content_hash, 0, 12).'...',
                round($result->overall_confidence, 3),
                $result->verification_status,
                $result->verified_at->format('Y-m-d H:i'),
                count($result->findings ?? []),
            ];
        }

        $this->table($tableData[0], array_slice($tableData, 1));
    }

    protected function displayTrendsTable(array $trends): void
    {
        $this->newLine();
        $this->info('=== VERIFICATION TRENDS ===');

        if (empty($trends)) {
            $this->line('No trend data available');

            return;
        }

        $tableData = [['Date', 'Verifications', 'Avg Confidence']];

        foreach ($trends as $date => $data) {
            $tableData[] = [
                $date,
                $data['count'],
                round($data['avg_confidence'], 3),
            ];
        }

        $this->table($tableData[0], array_slice($tableData, 1));
    }

    protected function convertStatisticsToCSV(array $statistics): string
    {
        $csv = "Metric,Value\n";
        $csv .= "Total Verifications,{$statistics['total_verifications']}\n";
        $csv .= 'Average Confidence,'.round($statistics['average_confidence'], 3)."\n";
        $csv .= 'Completion Rate,'.round($statistics['completion_rate'] * 100, 1)."%\n";

        // Status breakdown
        if (! empty($statistics['status_breakdown'])) {
            $csv .= "\nStatus Breakdown\n";
            $csv .= "Status,Count\n";
            foreach ($statistics['status_breakdown'] as $status => $count) {
                $csv .= "{$status},{$count}\n";
            }
        }

        return $csv;
    }

    protected function convertResultsToCSV($results): string
    {
        $csv = "ID,Content Hash,Confidence,Status,Verified At,Findings Count\n";

        foreach ($results as $result) {
            $csv .= sprintf(
                "%d,%s,%.3f,%s,%s,%d\n",
                $result->id,
                $result->content_hash,
                $result->overall_confidence,
                $result->verification_status,
                $result->verified_at->toISOString(),
                count($result->findings ?? [])
            );
        }

        return $csv;
    }

    protected function convertTrendsToCSV(array $trends): string
    {
        $csv = "Date,Verifications,Average Confidence\n";

        foreach ($trends as $date => $data) {
            $csv .= sprintf(
                "%s,%d,%.3f\n",
                $date,
                $data['count'],
                $data['avg_confidence']
            );
        }

        return $csv;
    }

    protected function convertExportToCSV(array $exportData): string
    {
        $csv = "ID,Content Hash,Confidence,Status,Verified At,Risk Level,Evidence Count,Summary\n";

        foreach ($exportData as $data) {
            $result = $data['result'];
            $riskAssessment = $data['risk_assessment'] ?? [];
            $summary = $data['summary'] ?? [];

            $csv .= sprintf(
                "%d,%s,%.3f,%s,%s,%s,%d,%s\n",
                $result['id'],
                $result['content_hash'],
                $result['overall_confidence'],
                $result['verification_status'],
                $result['verified_at'],
                $riskAssessment['risk_level'] ?? 'unknown',
                count($result['evidence'] ?? []),
                str_replace(["\n", "\r", ','], [' ', ' ', ';'], $summary['recommendation_summary'] ?? '')
            );
        }

        return $csv;
    }
}
