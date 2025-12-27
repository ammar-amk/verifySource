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

        // Reset stuck jobs before starting
        try {
            $this->call('queue:restart');
            usleep(500000);
        } catch (\Throwable $e) {
            $this->warn('Queue restart warning: '.$e->getMessage());
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
            // Windows: Properly detach the process using proc_open
            $this->startDetachedProcess('php artisan schedule:work');
        } else {
            // Linux/Mac: Start in background
            $process = Process::fromShellCommandline('php artisan schedule:work > /dev/null 2>&1 &');
            $process->setIdleTimeout(null);
            $process->setTimeout(null);
            $process->start();
            $process->disableOutput();
        }
        
        sleep(1);
        $this->line('  ✓ Scheduler started');
    }

    private function startQueueWorkers(): void
    {
        $this->info('[2/4] Starting Queue Workers...');
        
        // Start 5 crawling queue workers for parallel processing
        for ($i = 1; $i <= 5; $i++) {
            if (PHP_OS_FAMILY === 'Windows') {
                $this->startDetachedProcess("php artisan queue:work --queue=crawling --tries=3 --timeout=120 --max-jobs=50 --max-time=3600", "Queue Worker (Crawling #{$i})");
            } else {
                $process = Process::fromShellCommandline('php artisan queue:work --queue=crawling --tries=3 --timeout=600 > /dev/null 2>&1 &');
                $process->setIdleTimeout(null);
                $process->setTimeout(null);
                $process->start();
                $process->disableOutput();
            }
            usleep(200000); // 200ms delay between workers
        }
        
        // Start 2 default queue workers
        for ($i = 1; $i <= 2; $i++) {
            if (PHP_OS_FAMILY === 'Windows') {
                $this->startDetachedProcess("php artisan queue:work --queue=default --tries=3 --timeout=300", "Queue Worker (Default #{$i})");
            } else {
                $process = Process::fromShellCommandline('php artisan queue:work --queue=default --tries=3 --timeout=300 > /dev/null 2>&1 &');
                $process->setIdleTimeout(null);
                $process->setTimeout(null);
                $process->start();
                $process->disableOutput();
            }
            usleep(200000);
        }
        
        sleep(1);
        $this->line('  ✓ Queue workers started (5 crawling + 2 default)');
    }

    private function startCrawlerProcessor(): void
    {
        $this->info('[3/4] Starting Python Crawler Processor...');
        
        try {
            // Use non-blocking approach to avoid hanging the startup
            if (PHP_OS_FAMILY === 'Windows') {
                $this->startDetachedProcess('php artisan crawl:python:process --continuous');
            } else {
                $process = Process::fromShellCommandline('php artisan crawl:python:process --continuous > /dev/null 2>&1 &');
                $process->setIdleTimeout(null);
                $process->setTimeout(null);
                $process->start();
                $process->disableOutput();
            }
            
            sleep(1);
            $this->line('  ✓ Python crawler processor started');
            
            // Also start the job dispatcher to continuously feed the queue
            $this->info('[3.5/4] Starting Job Dispatcher...');
            if (PHP_OS_FAMILY === 'Windows') {
                $this->startDetachedProcess('php artisan crawl:dispatch-loop --batch=50 --sleep=10', 'Job Dispatcher');
            } else {
                $process = Process::fromShellCommandline('php artisan crawl:dispatch-loop --batch=50 --sleep=10 > /dev/null 2>&1 &');
                $process->setIdleTimeout(null);
                $process->setTimeout(null);
                $process->start();
                $process->disableOutput();
            }
            sleep(1);
            $this->line('  ✓ Job dispatcher started');
            
        } catch (\Exception $e) {
            $this->warn('  ⚠ Could not start Python crawler processor: ' . $e->getMessage());
            $this->line('    You can start it manually with: php artisan crawl:python:process --continuous');
        }
    }

    private function runSourceDiscovery(): void
    {
        $this->info('[4/4] Starting Continuous Source Discovery...');

        // Run source discovery continuously (every 6 hours)
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                // Create a PowerShell script that runs discovery in a loop
                $loopCommand = 'while ($true) { php artisan sources:discover --limit=50; Start-Sleep -Seconds 21600 }';
                $psCommand = sprintf('powershell -Command "%s"', addslashes($loopCommand));
                $this->startDetachedProcess($psCommand);
            } else {
                // Linux/Mac: Run in a loop with sleep
                $loopCommand = 'while true; do php artisan sources:discover --limit=50; sleep 21600; done';
                $process = Process::fromShellCommandline($loopCommand . ' > /dev/null 2>&1 &');
                $process->setIdleTimeout(null);
                $process->setTimeout(null);
                $process->start();
                $process->disableOutput();
            }
            
            sleep(1);
            $this->line('  ✓ Source discovery started (runs every 6 hours)');
        } catch (\Exception $e) {
            $this->warn('  ⚠ Source discovery failed to start: '.$e->getMessage());
            $this->line('    You can run it manually with: php artisan sources:discover --limit=50');
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
            $this->line('  ⚙️  Queue Workers (crawl) → 5 workers processing crawl jobs');
            $this->line('  ⚙️  Queue Workers (main)  → 2 workers processing verification jobs');
        }
        
        if (!$this->option('no-crawler')) {
            $this->line('  🕷️  Python Crawler       → Continuous job processor');
        }

        if (!$this->option('no-discovery')) {
            $this->line('  🔎 Source Discovery      → Runs every 6 hours (discovers 50 sources per run)');
        }
        
        $this->line("  🌐 Web Server           → http://{$host}:{$port}");
        
        $this->info('═══════════════════════════════════════════════════════');
    }

    private function startDetachedProcess(string $command): void
    {
        // Simply open a new visible terminal window - most reliable on Windows
        // Extract service name from command for window title
        preg_match('/artisan\s+([^\s]+)/', $command, $matches);
        $serviceName = $matches[1] ?? 'Service';
        
        $cmd = sprintf(
            'start "VerifySource - %s" cmd /k "%s"',
            $serviceName,
            $command
        );
        
        // Use pclose/popen for true async execution that doesn't wait
        pclose(popen($cmd, 'r'));
        
        // Small delay to ensure window opens
        usleep(250000); // 250ms
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
