<?php

namespace App\Console\Commands;

use App\Services\SourceManagementService;
use Illuminate\Console\Command;

class CredibilityInsightsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'credibility:insights';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show credibility insights and statistics for all sources';

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
        try {
            $insights = $this->sourceManagement->getCredibilityInsights();

            $this->info('=== CREDIBILITY INSIGHTS ===');
            $this->newLine();

            // Overall Statistics
            $this->info('📊 Overall Statistics');
            $this->line("Total Sources: {$insights['total_sources']}");
            $this->line("Assessed Sources: {$insights['assessed_sources']}");
            $this->line('Assessment Coverage: '.round($insights['assessment_coverage'], 1).'%');
            $this->line("Average Credibility: {$insights['average_credibility']}/100");
            $this->line("Recently Assessed (last week): {$insights['recently_assessed']}");

            if ($insights['needs_assessment'] > 0) {
                $this->warn("⚠️  Sources needing assessment: {$insights['needs_assessment']}");
            } else {
                $this->info('✅ All sources are up to date');
            }

            $this->newLine();

            // Credibility Distribution
            $this->info('🎯 Credibility Distribution');
            $distribution = $insights['distribution'];
            $total = array_sum($distribution);

            if ($total > 0) {
                $headers = ['Level', 'Count', 'Percentage'];
                $rows = [];

                foreach ($distribution as $level => $count) {
                    $percentage = round(($count / $total) * 100, 1);
                    $emoji = match ($level) {
                        'highly_credible' => '🟢',
                        'moderately_credible' => '🔵',
                        'average_credibility' => '🟡',
                        'low_credibility' => '🟠',
                        'questionable_credibility' => '🔴',
                        default => '⚪'
                    };
                    $rows[] = [$emoji.' '.ucfirst(str_replace('_', ' ', $level)), $count, "{$percentage}%"];
                }

                $this->table($headers, $rows);
            } else {
                $this->warn('No credibility assessments available');
            }

            $this->newLine();

            // Recommendations
            $this->info('💡 Recommendations');

            if ($insights['assessment_coverage'] < 50) {
                $this->warn('• Run credibility assessments on more sources (current coverage: '.round($insights['assessment_coverage'], 1).'%)');
            }

            if ($insights['needs_assessment'] > 0) {
                $this->warn("• {$insights['needs_assessment']} sources need credibility assessment updates");
                $this->line('  Run: php artisan credibility:refresh');
            }

            $veryLowCount = $distribution['questionable_credibility'] ?? 0;
            if ($veryLowCount > 0) {
                $this->warn("• {$veryLowCount} sources have questionable credibility - consider review or removal");
            }

            $highCredibilityCount = ($distribution['highly_credible'] ?? 0) + ($distribution['moderately_credible'] ?? 0);
            if ($total > 0 && ($highCredibilityCount / $total) < 0.3) {
                $this->warn('• Consider adding more high-credibility sources to improve overall quality');
            }

            if ($insights['average_credibility'] < 60) {
                $this->warn('• Overall credibility is below recommended threshold (60+)');
            } elseif ($insights['average_credibility'] >= 80) {
                $this->info('• Excellent overall source credibility! 🎉');
            }

        } catch (\Exception $e) {
            $this->error('Failed to get credibility insights: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
