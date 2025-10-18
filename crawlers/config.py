import os
from dotenv import load_dotenv

# Load environment variables from Laravel .env file
load_dotenv('../.env')

# Database Configuration
DATABASE_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'port': int(os.getenv('DB_PORT', 3306)),
    'user': os.getenv('DB_USERNAME', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_DATABASE', 'verifysource'),
    'charset': 'utf8mb4'
}

# Scrapy Settings
SCRAPY_SETTINGS = {
    'BOT_NAME': 'verifysource_crawler',
    'ROBOTSTXT_OBEY': True,
    'USER_AGENT': 'VerifySource Bot (+https://github.com/verifysource/crawler)',
    'CONCURRENT_REQUESTS': 16,
    'CONCURRENT_REQUESTS_PER_DOMAIN': 2,
    'DOWNLOAD_DELAY': 1,
    'RANDOMIZE_DOWNLOAD_DELAY': 0.5,
    'RETRY_TIMES': 3,
    'RETRY_HTTP_CODES': [500, 502, 503, 504, 408, 429],
    'HTTPERROR_ALLOWED_CODES': [404, 403],
    'COOKIES_ENABLED': False,
    'TELNETCONSOLE_ENABLED': False,
    'DEFAULT_REQUEST_HEADERS': {
        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language': 'en',
    },
    'ITEM_PIPELINES': {
        'crawlers.pipelines.ValidationPipeline': 300,
        'crawlers.pipelines.DeduplicationPipeline': 400,
        'crawlers.pipelines.DatabasePipeline': 500,
    },
    'DOWNLOADER_MIDDLEWARES': {
        'scrapy.downloadermiddlewares.useragent.UserAgentMiddleware': None,
        'crawlers.middlewares.RotateUserAgentMiddleware': 400,
        'scrapy.downloadermiddlewares.retry.RetryMiddleware': 550,
    }
}

# Content Extraction Settings
NEWSPAPER_CONFIG = {
    'browser_user_agent': 'VerifySource Bot',
    'request_timeout': 30,
    'number_threads': 10,
    'verbose': False,
    'memoize_articles': False,
    'fetch_images': False,
    'keep_article_html': True,
    'http_success_only': True,
}

# Rate Limiting
RATE_LIMIT = {
    'requests_per_minute': 60,
    'requests_per_hour': 1000,
    'delay_between_requests': 1.0,
    'respect_crawl_delay': True,
}

# Logging Configuration
LOGGING_CONFIG = {
    'version': 1,
    'disable_existing_loggers': False,
    'formatters': {
        'detailed': {
            'format': '%(asctime)s [%(levelname)s] %(name)s: %(message)s'
        },
    },
    'handlers': {
        'console': {
            'level': 'INFO',
            'class': 'logging.StreamHandler',
            'formatter': 'detailed',
        },
        'file': {
            'level': 'DEBUG',
            'class': 'logging.FileHandler',
            'filename': 'crawler.log',
            'formatter': 'detailed',
        },
    },
    'root': {
        'level': 'DEBUG',
        'handlers': ['console', 'file'],
    },
}

# User Agents for rotation
USER_AGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:89.0) Gecko/20100101 Firefox/89.0',
    'Mozilla/5.0 (X11; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0',
]