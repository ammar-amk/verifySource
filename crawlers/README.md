# VerifySource Python Crawlers

This directory contains the Python-based web crawling system using Scrapy and Newspaper3k.

## Setup

1. Create a virtual environment:
```bash
python -m venv venv
```

2. Activate the virtual environment:
```bash
# Windows
venv\Scripts\activate

# Linux/Mac
source venv/bin/activate
```

3. Install dependencies:
```bash
pip install -r requirements.txt
```

## Usage

The crawlers can be run directly or called from Laravel:

```bash
# Run a single URL crawl
python crawler.py --url "https://example.com/article"

# Run source crawl
python crawler.py --source-id 1

# Process crawl jobs from Laravel
python process_jobs.py
```

## Structure

- `crawler.py` - Main crawler script
- `spiders/` - Scrapy spider definitions
- `extractors/` - Newspaper3k content extractors
- `config.py` - Configuration and database connection
- `models.py` - Data models and database interaction