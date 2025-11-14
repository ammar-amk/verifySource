#!/usr/bin/env python3
"""
Simple URL Content Extractor for VerifySource
A lightweight alternative to the full Scrapy-based crawler
"""

import sys
import os
import argparse
import logging
import json
from datetime import datetime
from urllib.parse import urlparse

# Add the crawlers directory to Python path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

try:
    from extractors.newspaper_extractor import NewspaperExtractor
except ImportError as e:
    print(f"Error importing newspaper extractor: {e}")
    sys.exit(1)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s'
)
logger = logging.getLogger(__name__)


class SimpleURLExtractor:
    """Simple URL content extractor using only newspaper3k"""
    
    def __init__(self):
        self.extractor = NewspaperExtractor()
    
    def extract_url(self, url, source_id=None, crawl_job_id=None):
        """Extract content from a single URL"""
        logger.info(f"Extracting content from URL: {url}")
        
        try:
            # Use newspaper3k for content extraction
            article_data = self.extractor.extract_from_url(url)
            
            if article_data:
                # Add metadata
                article_data.update({
                    'source_id': source_id,
                    'crawl_job_id': crawl_job_id,
                    'scraped_at': datetime.now().isoformat(),
                    'extraction_method': 'newspaper3k_simple'
                })
                
                logger.info(f"Successfully extracted article: {article_data.get('title', 'No title')[:50]}")
                return article_data
            else:
                logger.warning(f"No content extracted from URL: {url}")
                return None
                
        except Exception as e:
            logger.error(f"Error extracting content from {url}: {e}")
            # Return a minimal result structure even on error
            return {
                'url': url,
                'title': None,
                'content': None,
                'error': str(e),
                'extraction_method': 'newspaper3k_simple_failed'
            }


def main():
    """Main CLI function"""
    parser = argparse.ArgumentParser(description='Simple URL Content Extractor')
    
    # Command options
    parser.add_argument('--url', required=True, help='URL to extract content from')
    parser.add_argument('--source-id', type=int, help='Source ID from database')
    parser.add_argument('--crawl-job-id', type=int, help='Crawl job ID from database')
    parser.add_argument('--output', help='Output file for results (JSON)')
    parser.add_argument('--verbose', '-v', action='store_true', help='Verbose logging')
    
    args = parser.parse_args()
    
    # Set log level
    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    
    # Initialize extractor
    extractor = SimpleURLExtractor()
    
    try:
        # Extract content from URL
        result = extractor.extract_url(
            args.url, 
            source_id=args.source_id,
            crawl_job_id=args.crawl_job_id
        )
        
        if result:
            if args.output:
                with open(args.output, 'w', encoding='utf-8') as f:
                    json.dump(result, f, indent=2, default=str, ensure_ascii=False)
                logger.info(f"Results saved to {args.output}")
            else:
                # Print to stdout
                print(json.dumps(result, indent=2, default=str, ensure_ascii=False))
        else:
            logger.error("Failed to extract content from URL")
            sys.exit(1)
    
    except KeyboardInterrupt:
        logger.info("Extraction interrupted by user")
        sys.exit(0)
    except Exception as e:
        logger.error(f"Extractor failed: {e}")
        sys.exit(1)


if __name__ == '__main__':
    main()
