# VerifySource - Automatic Crawling Setup Guide

## Problem: No Automatic Crawling

Your system has all the crawling infrastructure but **automatic scheduling was not configured**. I've now set it up!

---

## ✅ What I Fixed

### 1. Added Laravel Scheduler Configuration
**File:** `routes/console.php`

Configured automatic tasks:
- **Every 10 minutes**: Queue pending crawl jobs (processes 50 at a time)
- **Daily at 2 AM**: Schedule crawls for all active sources
- **Every hour**: Run Python crawler scheduler
- **Weekly (Sundays 3 AM)**: Clean up old crawl jobs (30+ days)
- **Every 30 minutes**: Health check logging

### 2. Created Helper Scripts
- `start-scheduler.bat` - Runs the Laravel scheduler continuously
- `start-services.bat` - Starts ALL required background services
- `CleanupCrawlJobs.php` - Command to clean old crawl jobs

---

## 🚀 How to Start Automatic Crawling

### Option 1: Start All Services (Recommended)
```bash
# Double-click or run:
start-services.bat
```

This opens 3 windows:
1. **Scheduler** - Triggers crawls every 10 minutes
2. **Crawling Queue** - Processes crawl jobs
3. **Default Queue** - Processes verification jobs

### Option 2: Manual Setup (Individual Commands)

**Terminal 1 - Start Scheduler:**
```bash
php artisan schedule:work
```

**Terminal 2 - Start Crawling Queue:**
```bash
php artisan queue:work --queue=crawling --tries=3 --timeout=600
```

**Terminal 3 - Start Default Queue:**
```bash
php artisan queue:work --queue=default --tries=3 --timeout=300
```

---

## 📋 Initial Setup (First Time Only)

Before automatic crawling works, you need sources in the database:

### 1. Add News Sources
```bash
php artisan tinker
```

Then paste this:
```php
$sources = [
    ['domain' => 'reuters.com', 'name' => 'Reuters', 'url' => 'https://www.reuters.com'],
    ['domain' => 'apnews.com', 'name' => 'Associated Press', 'url' => 'https://apnews.com'],
    ['domain' => 'bbc.com', 'name' => 'BBC News', 'url' => 'https://www.bbc.com/news'],
    ['domain' => 'cnn.com', 'name' => 'CNN', 'url' => 'https://www.cnn.com'],
    ['domain' => 'theguardian.com', 'name' => 'The Guardian', 'url' => 'https://www.theguardian.com'],
];

foreach ($sources as $sourceData) {
    \App\Models\Source::firstOrCreate(
        ['domain' => $sourceData['domain']],
        [
            'name' => $sourceData['name'],
            'url' => $sourceData['url'],
            'is_active' => true,
            'is_verified' => true,
            'credibility_score' => 0.85,
        ]
    );
}

echo "Created " . count($sources) . " sources\n";
exit;
```

### 2. Schedule Initial Crawls
```bash
# Schedule all sources for crawling
php artisan crawl:schedule --frequency=daily

# Queue the first batch immediately
php artisan crawl:schedule --queue --limit=50
```

### 3. Check Status
```bash
# See what's scheduled
php artisan schedule:list

# Check queue status
php artisan queue:monitor

# View crawl jobs
php artisan tinker
>>> \App\Models\CrawlJob::count()
>>> \App\Models\CrawlJob::where('status', 'pending')->count()
```

---

## 🔄 How It Works

1. **Scheduler runs every minute** (via `php artisan schedule:work`)
2. **Every 10 minutes**: Checks for pending crawl jobs and queues them
3. **Queue workers** pick up jobs and execute Python crawler
4. **Articles are extracted** and stored in database
5. **Content matching** becomes available for verification

---

## 🛠️ Useful Commands

```bash
# View scheduled tasks
php artisan schedule:list

# Test scheduler (see what would run now)
php artisan schedule:test

# Manually trigger scheduler once
php artisan schedule:run

# Queue pending crawls immediately
php artisan crawl:schedule --queue --limit=50

# Check crawl job status
php artisan tinker
>>> \App\Models\CrawlJob::selectRaw('status, COUNT(*) as count')->groupBy('status')->get()

# Clean up old jobs
php artisan crawl:cleanup --days=30 --dry-run

# View sources
php artisan tinker
>>> \App\Models\Source::where('is_active', true)->get(['id', 'domain', 'name'])
```

---

## ⚠️ Important Notes

### Windows Task Scheduler (For Production)
For 24/7 operation without keeping terminals open, set up Windows Task Scheduler:

1. Open Task Scheduler
2. Create Basic Task
3. **Name:** "VerifySource Scheduler"
4. **Trigger:** When computer starts
5. **Action:** Start a program
6. **Program:** `C:\path\to\php.exe`
7. **Arguments:** `artisan schedule:work`
8. **Start in:** `C:\Users\Ammar\verifySource`

Repeat for queue workers.

### Alternative: Install as Windows Service
Use tools like NSSM (Non-Sucking Service Manager):
```bash
nssm install VerifySourceScheduler "C:\path\to\php.exe" "artisan schedule:work"
nssm start VerifySourceScheduler
```

---

## 🐛 Troubleshooting

### Scheduler not running?
```bash
# Check if schedule:work is running
tasklist | findstr php

# View logs
tail -f storage/logs/laravel.log
```

### No jobs being processed?
```bash
# Check queue workers are running
php artisan queue:restart

# Check failed jobs
php artisan queue:failed
```

### Python crawler failing?
```bash
# Test Python directly
python crawlers/crawler.py --url "https://reuters.com"

# Check Python dependencies
pip install -r crawlers/requirements.txt
```

---

## ✅ Verification

After 10-20 minutes, check if it's working:

```bash
php artisan tinker
```

```php
// Check if articles are being crawled
\App\Models\Article::count()
\App\Models\Article::latest()->take(5)->get(['id', 'title', 'source_id', 'created_at'])

// Check sources
\App\Models\Source::with('articles')->get()->map(function($s) {
    return ['domain' => $s->domain, 'articles' => $s->articles->count()];
})
```

If you see articles being added, **automatic crawling is working! 🎉**

---

## Summary

**Before:** Manual crawling only, no automation
**After:** Automatic crawling every 10 minutes, 24/7

Just run `start-services.bat` and let it run in the background!
