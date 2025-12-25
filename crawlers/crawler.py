#!/usr/bin/env python3
"""
VerifySource Python Crawler
Main script for running Scrapy crawlers from command line or Laravel
"""

import sys
import os
import argparse
import logging
import json
from datetime import datetime

# Add project root to Python path so 'crawlers' package is importable
CURRENT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_ROOT = os.path.abspath(os.path.join(CURRENT_DIR, '..'))
if PROJECT_ROOT not in sys.path:
    sys.path.append(PROJECT_ROOT)

from scrapy.crawler import CrawlerProcess
from scrapy.utils.project import get_project_settings
from config import SCRAPY_SETTINGS, LOGGING_CONFIG
from spiders.base_spider import NewsSpider, BlogSpider, SinglePageSpider, SitemapSpider
from extractors.newspaper_extractor import NewspaperExtractor

# Configure logging
logging.config.dictConfig(LOGGING_CONFIG)
logger = logging.getLogger(__name__)


class VerifySourceCrawler:
    """Main crawler class"""
    
    def __init__(self):
        self.process = None
        self.extractor = NewspaperExtractor()
    
    def setup_scrapy_process(self):
        """Initialize Scrapy process with custom settings"""
        settings = get_project_settings()
        settings.update(SCRAPY_SETTINGS)
        self.process = CrawlerProcess(settings)
    
    def crawl_url(self, url, source_id=None, crawl_job_id=None):
        """Crawl a single URL"""
        logger.info(f"Crawling single URL: {url}")
        
        try:
            # Try Newspaper3k extraction first for single URLs
            article_data = self.extractor.extract_from_url(url)
            
            if article_data:
                article_data.update({
                    'source_id': source_id,
                    'crawl_job_id': crawl_job_id,
                    'scraped_at': datetime.utcnow().isoformat(),
                    'extraction_method': 'newspaper3k'
                })
                
                logger.info(f"Successfully extracted article: {article_data.get('title', 'No title')[:50]}")
                return article_data
            else:
                logger.warning(f"Newspaper3k extraction failed, falling back to Scrapy")
                
                # Fallback to Scrapy
                if not self.process:
                    self.setup_scrapy_process()
                
                self.process.crawl(
                    SinglePageSpider,
                    start_urls=[url],
                    source_id=source_id,
                    crawl_job_id=crawl_job_id
                )
                
                self.process.start(stop_after_crawl=True)
                
        except Exception as e:
            logger.error(f"Error crawling URL {url}: {e}")
            return None
    
    def crawl_source(self, source_id, source_url, source_type='news', max_pages=100):
        """Crawl an entire source"""
        logger.info(f"Crawling source {source_id}: {source_url}")
        
        try:
            if not self.process:
                self.setup_scrapy_process()
            
            spider_class = NewsSpider if source_type == 'news' else BlogSpider
            
            self.process.crawl(
                spider_class,
                start_urls=[source_url],
                source_id=source_id,
                base_url=source_url,
                max_pages=max_pages
            )
            
            self.process.start(stop_after_crawl=True)
            
        except Exception as e:
            logger.error(f"Error crawling source {source_id}: {e}")
    
    def crawl_sitemap(self, sitemap_url, source_id):
        """Crawl a sitemap to discover URLs"""
        logger.info(f"Crawling sitemap: {sitemap_url}")
        
        try:
            if not self.process:
                self.setup_scrapy_process()
            
            self.process.crawl(
                SitemapSpider,
                sitemap_url=sitemap_url,
                source_id=source_id
            )
            
            self.process.start(stop_after_crawl=True)
            
        except Exception as e:
            logger.error(f"Error crawling sitemap {sitemap_url}: {e}")


def main():
    """Main CLI function"""
    parser = argparse.ArgumentParser(description='VerifySource Python Crawler')
    
    # Command options
    parser.add_argument('--url', help='Single URL to crawl')
    parser.add_argument('--source-id', type=int, help='Source ID from database')
    parser.add_argument('--crawl-job-id', type=int, help='Crawl job ID from database')
    parser.add_argument('--source-url', help='Base URL of source to crawl')
    parser.add_argument('--source-type', choices=['news', 'blog'], default='news', help='Type of source')
    parser.add_argument('--sitemap', help='Sitemap URL to crawl')
    parser.add_argument('--max-pages', type=int, default=100, help='Maximum pages to crawl')
    parser.add_argument('--output', help='Output file for results (JSON)')
    parser.add_argument('--verbose', '-v', action='store_true', help='Verbose logging')
    
    args = parser.parse_args()
    
    # Set log level
    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    
    # Initialize crawler
    crawler = VerifySourceCrawler()
    
    try:
        if args.url:
            # Single URL crawling
            result = crawler.crawl_url(
                args.url, 
                source_id=args.source_id,
                crawl_job_id=args.crawl_job_id
            )
            
            if args.output and result:
                with open(args.output, 'w') as f:
                    json.dump(result, f, indent=2, default=str)
                logger.info(f"Results saved to {args.output}")
        
        elif args.sitemap:
            # Sitemap crawling
            crawler.crawl_sitemap(args.sitemap, args.source_id)
        
        elif args.source_url and args.source_id:
            # Full source crawling
            crawler.crawl_source(
                args.source_id,
                args.source_url,
                args.source_type,
                args.max_pages
            )
        
        else:
            parser.print_help()
            sys.exit(1)
    
    except KeyboardInterrupt:
        logger.info("Crawling interrupted by user")
        sys.exit(0)
    except Exception as e:
        logger.error(f"Crawler failed: {e}")
        sys.exit(1)


if __name__ == '__main__':
    main()