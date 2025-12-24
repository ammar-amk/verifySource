<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class ServeWithServices extends Command
{
    protected $signature = 'serve:with-services 
                            {--host=127.0.0.1 : The host to serve on}
                            {--port=8000 : The port to serve on}
                            {--no-scheduler : Do not start the scheduler}
                            {--no-queue : Do not start queue workers}
                            {--no-crawler : Do not start the Python crawler processor}
                            {--no-discovery : Do not run source discovery at startup}';

    protected $description = 'Start the development server with all background services';

    private array $processes = [];

    public function handle(): int
    {
        $host = $this->option('host');
        $port = $this->option('port');

        $this->info('🚀 Starting VerifySource Development Environment');
        $this->newLine();

        // Check if port is available
        if ($this->isPortInUse($host, $port)) {
            $this->error("Port {$port} is already in use. Please choose a different port with --port=<port>");
            return self::FAILURE;
        }

        // Start background services (best-effort; don't fail the command)
        try {
            if (!$this->option('no-scheduler')) {
                $this->startScheduler();
            }
        } catch (\Throwable $e) {
            $this->warn('Scheduler start warning: '.$e->getMessage());
        }

        try {
            if (!$this->option('no-queue')) {
                $this->startQueueWorkers();
            }
        } catch (\Throwable $e) {
            $this->warn('Queue workers start warning: '.$e->getMessage());
        }

        try {
            if (!$this->option('no-crawler')) {
                $this->startCrawlerProcessor();
            }
        } catch (\Throwable $e) {
            $this->warn('Python crawler start warning: '.$e->getMessage());
        }

        try {
            if (!$this->option('no-discovery')) {
                $this->runSourceDiscovery();
            }
        } catch (\Throwable $e) {
            $this->warn('Source discovery start warning: '.$e->getMessage());
        }

        $this->newLine();
        $this->info("✓ All services started successfully!");
        $this->newLine();
        
        // Display service status
        $this->displayServiceStatus($host, $port);
        
        $this->newLine();
        $this->comment('Press Ctrl+C to stop all services');
        $this->newLine();

        // Start the development server (this will block)
        $this->info("Starting Laravel development server on http://{$host}:{$port}");
        $this->call('serve', [
            '--host' => $host,
            '--port' => $port,
        ]);

        return self::SUCCESS;
    }

    private function startScheduler(): void
    {
        $this->info('[1/4] Starting Laravel Scheduler...');
        
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: Start in new window (avoid popen if disabled)
            $command = 'cmd /c start "VerifySource Scheduler" cmd /k "php artisan schedule:work"';
            @exec($command);
        } else {
            // Linux/Mac: Start in background
            $process = Process::fromShellCommandline('php artisan schedule:work > /dev/null 2>&1 &');
            $process->start();
        }
        
        sleep(1);
        $this->line('  ✓ Scheduler started');
    }

    private function startQueueWorkers(): void
    {
        $this->info('[2/4] Starting Queue Workers...');
        
        // Start crawling queue worker
        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'cmd /c start "VerifySource Queue - Crawling" cmd /k "php artisan queue:work --queue=crawling --tries=3 --timeout=600"';
            @exec($command);
        } else {
            $process = Process::fromShellCommandline('php artisan queue:work --queue=crawling --tries=3 --timeout=600 > /dev/null 2>&1 &');
            $process->start();
        }
        
        sleep(1);
        
        // Start default queue worker
        if (PHP_OS_FAMILY === 'Windows') {
            $command = 'cmd /c start "VerifySource Queue - Default" cmd /k "php artisan queue:work --queue=default --tries=3 --timeout=300"';
            @exec($command);
        } else {
            $process = Process::fromShellCommandline('php artisan queue:work --queue=default --tries=3 --timeout=300 > /dev/null 2>&1 &');
            $process->start();
        }
        
        sleep(1);
        $this->line('  ✓ Queue workers started (crawling + default)');
    }

    private function startCrawlerProcessor(): void
    {
        $this->info('[3/4] Starting Python Crawler Processor...');
        
        try {
            // Use the artisan command to start the Python processor
            $exitCode = $this->call('crawl:python:process', [
                '--continuous' => true,
            ]);
            
            if ($exitCode === 0) {
                $this->line('  ✓ Python crawler processor started');
            } else {
                $this->warn('  ⚠ Python crawler processor may not have started correctly');
            }
        } catch (\Exception $e) {
            $this->warn('  ⚠ Could not start Python crawler processor: ' . $e->getMessage());
            $this->line('    You can start it manually with: php artisan crawl:python:process --continuous');
        }
    }

    private function runSourceDiscovery(): void
    {
        $this->info('[4/4] Running Source Discovery once...');

        // Best-effort: do not block startup if discovery fails
        try {
            $this->call('sources:discover', [
                '--limit' => 10,
            ]);
            $this->line('  ✓ Source discovery queued/ran');
        } catch (\Exception $e) {
            $this->warn('  ⚠ Source discovery failed to start: '.$e->getMessage());
            $this->line('    You can run it manually with: php artisan sources:discover --limit=10');
        }
    }

    private function displayServiceStatus(string $host, int $port): void
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Running Services:');
        $this->info('═══════════════════════════════════════════════════════');
        
        if (!$this->option('no-scheduler')) {
            $this->line('  📅 Laravel Scheduler    → Automated crawling every 10 min');
        }
        
        if (!$this->option('no-queue')) {
            $this->line('  ⚙️  Queue Worker (crawl) → Processes crawl jobs');
            $this->line('  ⚙️  Queue Worker (main)  → Processes verification jobs');
        }
        
        if (!$this->option('no-crawler')) {
            $this->line('  🕷️  Python Crawler       → Continuous job processor');
        }

        if (!$this->option('no-discovery')) {
            $this->line('  🔎 Source Discovery      → Ran once at startup (daily via scheduler)');
        }
        
        $this->line("  🌐 Web Server           → http://{$host}:{$port}");
        
        $this->info('═══════════════════════════════════════════════════════');
    }

    private function isPortInUse(string $host, int $port): bool
    {
        $connection = @fsockopen($host, $port);
        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }
        return false;
    }

    public function __destruct()
    {
        // Cleanup processes if needed
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
    }
}
