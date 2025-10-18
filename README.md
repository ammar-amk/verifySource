VerifySource — Open-Source Content Provenance & Verification Platform

VerifySource is an open-source web platform built with Laravel that helps users trace the original source of online content — articles, posts, or text excerpts. It empowers individuals, journalists, researchers, and fact-checkers to verify the authenticity and origin of information circulating across the web.

The system works by combining web crawling, content hashing, full-text and semantic search, and timestamp verification using open APIs like the Wayback Machine. When a user submits a piece of text or a URL, VerifySource scans its indexed database and the broader web to identify where the content first appeared, estimate its credibility, and display the earliest verified publication.

🔍 Core Features

Source Verification: Paste any text or link to find the earliest known source.

Crawling Engine: Continuously scrapes and indexes news sites, blogs, and public sources using open-source crawlers.

Semantic Matching: Uses vector search to detect rephrased or slightly modified duplicates.

Timestamp Provenance: Integrates with the Internet Archive to verify historical availability.

Credibility Scoring: Ranks results based on domain trustworthiness and publish dates.

Open API: Offers a public endpoint for third-party verification tools and browser extensions.

Privacy-Respecting: No tracking, no ads, fully open and transparent.

⚙️ Technology Stack

Backend: Laravel (PHP 8+), Redis, PostgreSQL

Search: Meilisearch (full-text) + Qdrant (semantic embeddings)

Crawlers: Scrapy & Newspaper3k (Python)

Frontend: Laravel Blade + Livewire (or optional Vue.js)

APIs: Wayback Machine, DuckDuckGo, News APIs (optional)

Deployment: Docker Compose + Nginx

🌍 Open-Source Philosophy

VerifySource is built entirely with open-source tools under the MIT License, ensuring transparency, collaboration, and accessibility. Developers can self-host the platform, extend its functionality, or contribute to improving misinformation detection and source tracing globally.

🚀 Mission

To create a trust infrastructure for online content — giving everyone the ability to verify what they read, trace where it originated, and reduce the spread of misinformation through open technology.



Phase 1: Foundation & Core Infrastructure ✅ - Set up database schema, basic models, and core Laravel structure
Phase 2: Content Management System ✅ - Create models for articles, sources, and content verification
Phase 3: Web Crawling Engine ✅ - Implement content scraping and indexing system
Phase 4: Search & Matching - Set up Meilisearch and semantic search with Qdrant
Phase 5: Verification Engine - Build content verification and provenance tracking
Phase 6: Frontend Interface - Create user interface with Laravel Blade and Livewire 
Phase 7: External API Integration - Integrate Wayback Machine and other verification APIs
Phase 8: Credibility & Scoring System - Implement domain trustworthiness and content scoring
Phase 9: Public API - Create open API endpoints for third-party integrations
Phase 10: Deployment & DevOps - Set up Docker, Nginx, and production deployment

## Phase 3 Implementation Details

### Web Crawling Engine Components

**Core Services:**
- `CrawlJobService` - Manages crawl job lifecycle, scheduling, and tracking
- `WebScraperService` - Handles HTTP requests, content scraping, and sitemap processing
- `ContentExtractionService` - Extracts and cleans content from scraped HTML
- `CrawlerOrchestrationService` - Orchestrates the entire crawling process and content indexing
- `CrawlSchedulingService` - Manages job queues and automated scheduling

**Queue Jobs:**
- `ProcessCrawlJobQueue` - Processes individual crawl jobs asynchronously
- `ScheduledSourceCrawl` - Handles recurring source crawls

**Artisan Commands:**
- `crawl:process` - Process pending crawl jobs
- `crawl:source {source} --all --immediate` - Crawl specific or all sources
- `crawl:index` - Index scraped content for search and deduplication
- `crawl:stats` - Display crawling system statistics
- `crawl:cleanup --days=30 --retry-failed` - Clean up old jobs and retry failed ones
- `crawl:schedule --queue --setup-recurring` - Manage crawl scheduling and queues

**Key Features:**
- Intelligent content extraction from HTML using multiple selectors
- Automatic duplicate detection and content deduplication
- Configurable retry logic with exponential backoff
- Sitemap discovery and processing
- Content quality analysis and scoring
- Rate limiting and respectful crawling practices
- Comprehensive logging and monitoring
- Queue-based asynchronous processing