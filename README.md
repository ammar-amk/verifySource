# VerifySource

A comprehensive news and content verification platform that combines intelligent web crawling with advanced verification services to help users assess the credibility of news articles and online content.

## 🎯 Overview

VerifySource is a full-stack application that crawls news sources, analyzes content using AI-powered embeddings, and provides verification services for text, URLs, and documents. The platform helps users determine the credibility and authenticity of information by cross-referencing content against a continuously updated database of verified news sources.

## ✨ Features

### Content Verification
- **Multi-Format Support**: Verify content from text, URLs, or uploaded files (TXT, PDF, DOCX)
- **AI-Powered Analysis**: Uses sentence transformers and embeddings for semantic similarity detection
- **Credibility Scoring**: Provides confidence scores based on source reputation and content analysis
- **Duplicate Detection**: Identifies similar or duplicate content across sources

### Web Crawling System
- **Automated Crawling**: Python-based crawlers using Scrapy and Newspaper3k
- **Source Management**: Track and manage news sources with credibility scores
- **Content Extraction**: Extract articles, metadata, authors, and publish dates
- **Queue-Based Processing**: Asynchronous job processing for scalable crawling

### Modern Web Interface
- **Real-Time Updates**: Built with Laravel Livewire for reactive user experiences
- **Responsive Design**: Tailwind CSS for modern, mobile-friendly UI
- **User-Friendly Forms**: Intuitive verification interface with multiple input options

## 🛠️ Technology Stack

### Backend
- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: SQLite (configurable for MySQL/PostgreSQL)
- **Queue System**: Database-driven job queues
- **API Client**: Guzzle HTTP

### Frontend
- **UI Framework**: Laravel Livewire 3.6
- **CSS Framework**: Tailwind CSS 4.0
- **Build Tool**: Vite 7.0
- **Asset Management**: Laravel Vite Plugin

### Python Services
- **Web Crawling**: Scrapy 2.11+
- **Content Extraction**: Newspaper3k
- **AI/ML**: 
  - Sentence Transformers
  - PyTorch
  - Transformers (Hugging Face)
- **Vector Operations**: FAISS
- **NLP**: NLTK, spaCy

### Search & Analytics
- **Search Engine**: Meilisearch
- **Embeddings**: Sentence Transformers for semantic search

## 📋 Prerequisites

- **PHP**: 8.2 or higher
- **Composer**: Latest version
- **Node.js**: 18+ and npm
- **Python**: 3.8+ with pip
- **Database**: SQLite (default) or MySQL/PostgreSQL
- **Meilisearch**: For search functionality (optional)

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/ammar-amk/verifySource.git
cd verifySource
```

### 2. Quick Setup (Recommended)

Use the automated setup script:

```bash
composer setup
```

This will:
- Install PHP dependencies
- Create `.env` file from example
- Generate application key
- Run database migrations
- Install Node.js dependencies
- Build frontend assets

### 3. Manual Setup (Alternative)

If you prefer manual setup:

```bash
# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Install Node.js dependencies
npm install

# Build frontend assets
npm run build
```

### 4. Python Crawlers Setup

```bash
# Navigate to crawlers directory
cd crawlers

# Create virtual environment
python -m venv venv

# Activate virtual environment
# On Windows:
venv\Scripts\activate
# On Linux/Mac:
source venv/bin/activate

# Install Python dependencies
pip install -r requirements.txt

# Return to project root
cd ..
```

### 5. Database Setup

The default configuration uses SQLite. The database file will be created automatically at `database/database.sqlite`.

For MySQL/PostgreSQL, update your `.env` file:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=verifysource
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Then run migrations:

```bash
php artisan migrate
```

## 💻 Usage

### Development Mode

Start all development services simultaneously:

```bash
composer dev
```

This starts:
- Laravel development server (`php artisan serve`) on http://localhost:8000
- Queue worker (`php artisan queue:listen`)
- Log viewer (`php artisan pail`)
- Vite dev server for hot module reloading

### Individual Services

Start services separately:

```bash
# Start Laravel server
php artisan serve

# Start queue worker (in separate terminal)
php artisan queue:listen

# Start Vite dev server (in separate terminal)
npm run dev
```

### Running Crawlers

```bash
# Activate Python virtual environment
cd crawlers
source venv/bin/activate  # or venv\Scripts\activate on Windows

# Crawl a single URL
python crawler.py --url "https://example.com/article"

# Crawl a specific source
python crawler.py --source-id 1

# Process crawl jobs from Laravel queue
python process_jobs.py
```

### Building for Production

```bash
# Build frontend assets
npm run build

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 🧪 Testing

Run the test suite:

```bash
composer test
```

Or use artisan directly:

```bash
php artisan test
```

Run specific test suites:

```bash
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

## 🔧 Configuration

### Environment Variables

Key configuration options in `.env`:

```env
# Application
APP_NAME=VerifySource
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=sqlite

# Queue
QUEUE_CONNECTION=database

# Cache
CACHE_STORE=database

# Session
SESSION_DRIVER=database
```

### Python Crawlers

The crawlers automatically read database configuration from the Laravel `.env` file. Ensure your `.env` has:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=verifysource
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Additional crawler settings can be customized in `crawlers/config.py`.

## 📁 Project Structure

```
verifySource/
├── app/                    # Laravel application code
│   ├── Console/           # Artisan commands
│   ├── Http/              # Controllers and middleware
│   ├── Jobs/              # Queue jobs
│   ├── Livewire/          # Livewire components
│   ├── Models/            # Eloquent models
│   ├── Observers/         # Model observers
│   ├── Providers/         # Service providers
│   └── Services/          # Business logic services
├── crawlers/              # Python web crawling system
│   ├── spiders/           # Scrapy spider definitions
│   ├── extractors/        # Content extraction logic
│   ├── crawler.py         # Main crawler script
│   ├── config.py          # Crawler configuration
│   └── process_jobs.py    # Job processor
├── database/              # Database files and migrations
│   ├── factories/         # Model factories
│   ├── migrations/        # Database migrations
│   └── seeders/           # Database seeders
├── public/                # Public web assets
├── resources/             # Views, CSS, JS
│   ├── css/              # Stylesheets
│   ├── js/               # JavaScript files
│   └── views/            # Blade templates
├── routes/                # Route definitions
├── storage/               # Application storage
└── tests/                 # Test suites
```

## 🗄️ Database Schema

### Key Tables

- **sources**: News sources with credibility scores
- **articles**: Crawled articles and content
- **content_hashes**: Content deduplication
- **verification_requests**: User verification queries
- **verification_results**: Verification analysis results
- **crawl_jobs**: Crawling job queue

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Code Style

- **PHP**: Follow PSR-12 coding standards (enforced by Laravel Pint)
- **JavaScript**: Follow project ESLint configuration
- **Python**: Follow PEP 8 style guide

Run code formatters:

```bash
# Format PHP code
./vendor/bin/pint

# Lint crawlers (if configured)
# See crawler-lint.yml for details
```

## 📝 License

This project is licensed under the MIT License - see the [composer.json](composer.json) file for details.

## 🙏 Acknowledgments

- Built with [Laravel](https://laravel.com)
- Styled with [Tailwind CSS](https://tailwindcss.com)
- Powered by [Livewire](https://livewire.laravel.com)
- Crawling with [Scrapy](https://scrapy.org) and [Newspaper3k](https://newspaper.readthedocs.io)
- AI models from [Hugging Face](https://huggingface.co)

## 📧 Support

For questions, issues, or suggestions:
- Open an [issue](https://github.com/ammar-amk/verifySource/issues)
- Submit a [pull request](https://github.com/ammar-amk/verifySource/pulls)

---

**VerifySource** - Empowering truth through intelligent content verification
