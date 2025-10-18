import logging
import mysql.connector
from mysql.connector import Error
from datetime import datetime
import json
from config import DATABASE_CONFIG
from extractors.newspaper_extractor import ContentValidator

logger = logging.getLogger(__name__)


class ValidationPipeline:
    """Validates scraped content before processing"""
    
    def process_item(self, item, spider):
        if ContentValidator.is_valid_article(item):
            logger.info(f"Article validation passed: {item.get('title', 'No title')[:50]}")
            return item
        else:
            logger.warning(f"Article validation failed: {item.get('url', 'No URL')}")
            raise DropItem(f"Invalid article content: {item.get('url')}")


class DeduplicationPipeline:
    """Prevents duplicate content from being saved"""
    
    def __init__(self):
        self.seen_hashes = set()
        self.seen_urls = set()
    
    def process_item(self, item, spider):
        content_hash = item.get('content_hash')
        url = item.get('url')
        
        # Check for duplicate content hash
        if content_hash and content_hash in self.seen_hashes:
            logger.info(f"Duplicate content detected (hash): {url}")
            raise DropItem(f"Duplicate content hash: {content_hash}")
        
        # Check for duplicate URL
        if url in self.seen_urls:
            logger.info(f"Duplicate URL detected: {url}")
            raise DropItem(f"Duplicate URL: {url}")
        
        # Add to seen sets
        if content_hash:
            self.seen_hashes.add(content_hash)
        if url:
            self.seen_urls.add(url)
        
        return item


class DatabasePipeline:
    """Saves items to MySQL database"""
    
    def __init__(self):
        self.connection = None
        self.cursor = None
    
    def open_spider(self, spider):
        """Initialize database connection when spider opens"""
        try:
            self.connection = mysql.connector.connect(**DATABASE_CONFIG)
            self.cursor = self.connection.cursor()
            logger.info("Database connection established")
        except Error as e:
            logger.error(f"Error connecting to database: {e}")
    
    def close_spider(self, spider):
        """Close database connection when spider closes"""
        if self.cursor:
            self.cursor.close()
        if self.connection and self.connection.is_connected():
            self.connection.close()
            logger.info("Database connection closed")
    
    def process_item(self, item, spider):
        """Save item to database"""
        try:
            # Handle sitemap URLs differently
            if item.get('found_in_sitemap'):
                self._save_discovered_url(item)
            else:
                self._save_article(item)
            
            return item
            
        except Error as e:
            logger.error(f"Database error: {e}")
            raise DropItem(f"Error saving item: {e}")
    
    def _save_article(self, item):
        """Save article to database"""
        # First, ensure source exists
        source_id = item.get('source_id')
        if not source_id:
            raise DropItem("No source_id provided for article")
        
        # Check if article already exists
        check_query = "SELECT id FROM articles WHERE url = %s OR content_hash = %s"
        self.cursor.execute(check_query, (item.get('url'), item.get('content_hash')))
        
        if self.cursor.fetchone():
            logger.info(f"Article already exists in database: {item.get('url')}")
            return
        
        # Insert article
        insert_query = """
        INSERT INTO articles (
            source_id, url, title, content, excerpt, author, published_at,
            crawled_at, content_hash, language, metadata, is_processed, is_duplicate
        ) VALUES (
            %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
        )
        """
        
        # Prepare metadata
        metadata = {
            'top_image': item.get('top_image'),
            'images': item.get('images', []),
            'videos': item.get('videos', []),
            'keywords': item.get('keywords', []),
            'summary': item.get('summary'),
            'meta_description': item.get('meta_description'),
            'meta_keywords': item.get('meta_keywords'),
            'canonical_link': item.get('canonical_link'),
            'source_url': item.get('source_url'),
            'word_count': item.get('word_count'),
            'quality_score': item.get('quality_score'),
            'quality_factors': item.get('quality_factors', []),
            'quality_issues': item.get('quality_issues', []),
            'spider_name': item.get('spider_name'),
            'scraped_at': item.get('scraped_at'),
        }
        
        # Parse published date
        published_at = None
        if item.get('published_at'):
            try:
                if isinstance(item['published_at'], str):
                    published_at = datetime.fromisoformat(item['published_at'].replace('Z', '+00:00'))
                else:
                    published_at = item['published_at']
            except (ValueError, TypeError) as e:
                logger.warning(f"Could not parse published_at: {item.get('published_at')}, error: {e}")
        
        values = (
            source_id,
            item.get('url'),
            item.get('title'),
            item.get('content'),
            item.get('excerpt'),
            item.get('authors'),
            published_at,
            datetime.utcnow(),  # crawled_at
            item.get('content_hash'),
            item.get('language', 'en'),
            json.dumps(metadata),
            False,  # is_processed
            False   # is_duplicate
        )
        
        self.cursor.execute(insert_query, values)
        self.connection.commit()
        
        article_id = self.cursor.lastrowid
        logger.info(f"Article saved with ID {article_id}: {item.get('title', 'No title')[:50]}")
        
        # Update crawl job status if provided
        crawl_job_id = item.get('crawl_job_id')
        if crawl_job_id:
            self._update_crawl_job_success(crawl_job_id, article_id)
    
    def _save_discovered_url(self, item):
        """Save discovered URL as a new crawl job"""
        source_id = item.get('source_id')
        url = item.get('url')
        
        if not source_id or not url:
            return
        
        # Check if crawl job already exists for this URL
        check_query = "SELECT id FROM crawl_jobs WHERE url = %s AND status IN ('pending', 'running')"
        self.cursor.execute(check_query, (url,))
        
        if self.cursor.fetchone():
            logger.debug(f"Crawl job already exists for URL: {url}")
            return
        
        # Insert new crawl job
        insert_query = """
        INSERT INTO crawl_jobs (
            source_id, url, status, priority, retry_count, max_retries,
            metadata, scheduled_at, created_at, updated_at
        ) VALUES (
            %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
        )
        """
        
        metadata = {
            'discovered_from_sitemap': True,
            'sitemap_url': item.get('sitemap_url'),
            'discovered_at': item.get('discovered_at'),
        }
        
        now = datetime.utcnow()
        
        values = (
            source_id,
            url,
            'pending',  # status
            -1,  # priority (lower for discovered URLs)
            0,   # retry_count
            3,   # max_retries
            json.dumps(metadata),
            now,  # scheduled_at
            now,  # created_at
            now   # updated_at
        )
        
        self.cursor.execute(insert_query, values)
        self.connection.commit()
        
        logger.info(f"Discovered URL saved as crawl job: {url}")
    
    def _update_crawl_job_success(self, crawl_job_id, article_id):
        """Update crawl job status to completed"""
        update_query = """
        UPDATE crawl_jobs 
        SET status = %s, completed_at = %s, 
            metadata = JSON_SET(COALESCE(metadata, '{}'), '$.article_id', %s)
        WHERE id = %s
        """
        
        self.cursor.execute(update_query, ('completed', datetime.utcnow(), article_id, crawl_job_id))
        self.connection.commit()
        
        logger.info(f"Crawl job {crawl_job_id} marked as completed with article {article_id}")


class DropItem(Exception):
    """Exception to drop an item from the pipeline"""
    pass