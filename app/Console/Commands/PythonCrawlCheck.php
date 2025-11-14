<?php

namespace App\Console\Commands;

use App\Services\PythonCrawlerService;
use Illuminate\Console\Command;

class PythonCrawlCheck extends Command
{
    protected $signature = 'crawl:python:check';

    protected $description = 'Check Python crawler environment and requirements';

    public function handle(PythonCrawlerService $pythonCrawler): int
    {
        $this->info('Checking Python crawler environment...');

        $envCheck = $pythonCrawler->checkPythonEnvironment();

        $this->line('');
        $this->info('=== Python Crawler Environment Check ===');

        foreach ($envCheck['checks'] as $check => $result) {
            $status = $result['available'] ?? $result['exists'] ?? $result['installed'] ?? false;
            $icon = $status ? '✓' : '✗';
            $color = $status ? 'info' : 'error';

            $this->line('');
            $this->$color("{$icon} ".ucwords(str_replace('_', ' ', $check)));

            if (isset($result['path'])) {
                $this->line("    Path: {$result['path']}");
            }

            if (isset($result['version'])) {
                $this->line("    Version: {$result['version']}");
            }

            if (isset($result['output']) && $result['output'] !== 'OK') {
                $this->line("    Output: {$result['output']}");
            }

            if (isset($result['error'])) {
                $this->line("    Error: {$result['error']}");
            }
        }

        $this->line('');

        if ($envCheck['ready']) {
            $this->info('✓ Python crawler environment is ready!');

            $this->line('');
            $this->info('Available commands:');
            $this->line('- php artisan crawl:python:process --max-jobs=10');
            $this->line('- php artisan crawl:python:process --continuous');
            $this->line("- php artisan crawl:python:url 'https://example.com'");

        } else {
            $this->error('✗ Python crawler environment has issues!');

            $this->line('');
            $this->info('Setup instructions:');
            $this->line('1. Ensure Python 3.7+ is installed');
            $this->line('2. Navigate to the crawlers directory:');
            $this->line('   cd '.base_path('crawlers'));
            $this->line('3. Create a virtual environment:');
            $this->line('   python -m venv venv');
            $this->line('4. Activate the virtual environment:');
            $this->line('   # Windows: venv\\Scripts\\activate');
            $this->line('   # Linux/Mac: source venv/bin/activate');
            $this->line('5. Install requirements:');
            $this->line('   pip install -r requirements.txt');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
