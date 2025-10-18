#!/usr/bin/env python3
"""
Process crawl jobs from Laravel database
"""

import sys
import os
import logging
import json
import time
from datetime import datetime

# Add the crawlers directory to Python path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

try:
    import mysql.connector
    from mysql.connector import Error
except ImportError:
    print("mysql-connector-python is required. Install with: pip install mysql-connector-python")
    sys.exit(1)

from config import DATABASE_CONFIG, LOGGING_CONFIG
from crawler import VerifySourceCrawler

# Configure logging
logging.config.dictConfig(LOGGING_CONFIG)
logger = logging.getLogger(__name__)


class CrawlJobProcessor:
    """Processes crawl jobs from Laravel database"""
    
    def __init__(self):
        self.connection = None
        self.cursor = None
        self.crawler = VerifySourceCrawler()
        
    def connect_database(self):
        """Connect to MySQL database"""
        try:
            self.connection = mysql.connector.connect(**DATABASE_CONFIG)
            self.cursor = self.connection.cursor(dictionary=True)
            logger.info("Connected to database")
        except Error as e:
            logger.error(f"Database connection failed: {e}")
            raise
    
    def disconnect_database(self):
        """Disconnect from database"""
        if self.cursor:
            self.cursor.close()
        if self.connection and self.connection.is_connected():
            self.connection.close()
            logger.info("Disconnected from database")
    
    def get_pending_jobs(self, limit=10):
        """Get pending crawl jobs from database"""
        query = """
        SELECT cj.*, s.url as source_url, s.domain, s.name as source_name
        FROM crawl_jobs cj
        LEFT JOIN sources s ON cj.source_id = s.id
        WHERE cj.status = 'pending' AND cj.scheduled_at <= NOW()
        ORDER BY cj.priority DESC, cj.scheduled_at ASC
        LIMIT %s
        """
        
        self.cursor.execute(query, (limit,))
        return self.cursor.fetchall()
    
    def update_job_status(self, job_id, status, error_message=None, metadata=None):
        """Update crawl job status"""
        update_fields = ['status = %s']
        values = [status]
        
        if status == 'running':
            update_fields.append('started_at = %s')
            values.append(datetime.utcnow())
        elif status in ['completed', 'failed']:
            update_fields.append('completed_at = %s')
            values.append(datetime.utcnow())
        
        if error_message:
            update_fields.append('error_message = %s')
            values.append(error_message)
        
        if metadata:
            update_fields.append('metadata = JSON_SET(COALESCE(metadata, "{}"), %s)')
            for key, value in metadata.items():
                values.append(f'$.{key}')
                values.append(json.dumps(value) if isinstance(value, (dict, list)) else str(value))
        
        query = f"UPDATE crawl_jobs SET {', '.join(update_fields)}, updated_at = %s WHERE id = %s"
        values.extend([datetime.utcnow(), job_id])
        
        self.cursor.execute(query, values)
        self.connection.commit()
    
    def increment_retry_count(self, job_id):
        """Increment retry count for failed job"""
        query = "UPDATE crawl_jobs SET retry_count = retry_count + 1 WHERE id = %s"
        self.cursor.execute(query, (job_id,))
        self.connection.commit()
    
    def process_job(self, job):
        """Process a single crawl job"""
        job_id = job['id']
        url = job['url']
        source_id = job['source_id']
        
        logger.info(f"Processing crawl job {job_id}: {url}")
        
        try:
            # Mark job as running
            self.update_job_status(job_id, 'running')
            
            # Check if this is a sitemap URL
            if 'sitemap' in url.lower():
                self.crawler.crawl_sitemap(url, source_id)
                result_metadata = {'crawl_type': 'sitemap'}
            else:
                # Regular URL crawling
                result = self.crawler.crawl_url(url, source_id, job_id)
                result_metadata = {
                    'crawl_type': 'single_url',
                    'extraction_successful': result is not None,
                    'title': result.get('title') if result else None,
                    'content_length': len(result.get('content', '')) if result else 0
                }
            
            # Mark job as completed
            self.update_job_status(job_id, 'completed', metadata=result_metadata)
            logger.info(f"Crawl job {job_id} completed successfully")
            
        except Exception as e:
            error_msg = str(e)
            logger.error(f"Crawl job {job_id} failed: {error_msg}")
            
            # Increment retry count
            self.increment_retry_count(job_id)
            
            # Check if we should retry or mark as failed
            if job['retry_count'] + 1 < job['max_retries']:
                # Reset to pending for retry
                self.update_job_status(job_id, 'pending', error_message=error_msg)
                logger.info(f"Crawl job {job_id} queued for retry ({job['retry_count'] + 1}/{job['max_retries']})")
            else:
                # Mark as permanently failed
                self.update_job_status(job_id, 'failed', error_message=error_msg)
                logger.error(f"Crawl job {job_id} permanently failed after {job['max_retries']} retries")
    
    def run_processor(self, max_jobs=None, continuous=False, sleep_interval=60):
        """Run the job processor"""
        processed_count = 0
        
        try:
            self.connect_database()
            
            while True:
                # Get pending jobs
                pending_jobs = self.get_pending_jobs(limit=10)
                
                if not pending_jobs:
                    if continuous:
                        logger.info(f"No pending jobs, sleeping for {sleep_interval} seconds")
                        time.sleep(sleep_interval)
                        continue
                    else:
                        logger.info("No pending jobs found")
                        break
                
                # Process each job
                for job in pending_jobs:
                    if max_jobs and processed_count >= max_jobs:
                        logger.info(f"Reached maximum job limit ({max_jobs})")
                        return processed_count
                    
                    self.process_job(job)
                    processed_count += 1
                    
                    # Small delay between jobs
                    time.sleep(2)
                
                if not continuous:
                    break
                    
                # Sleep before checking for more jobs
                if continuous:
                    time.sleep(sleep_interval)
            
            return processed_count
            
        finally:
            self.disconnect_database()


def main():
    """Main function"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Process VerifySource crawl jobs')
    parser.add_argument('--max-jobs', type=int, help='Maximum number of jobs to process')
    parser.add_argument('--continuous', action='store_true', help='Run continuously')
    parser.add_argument('--sleep-interval', type=int, default=60, help='Sleep interval in continuous mode (seconds)')
    parser.add_argument('--verbose', '-v', action='store_true', help='Verbose logging')
    
    args = parser.parse_args()
    
    # Set log level
    if args.verbose:
        logging.getLogger().setLevel(logging.DEBUG)
    
    processor = CrawlJobProcessor()
    
    try:
        processed = processor.run_processor(
            max_jobs=args.max_jobs,
            continuous=args.continuous,
            sleep_interval=args.sleep_interval
        )
        
        logger.info(f"Processed {processed} crawl jobs")
        
    except KeyboardInterrupt:
        logger.info("Job processor interrupted by user")
    except Exception as e:
        logger.error(f"Job processor failed: {e}")
        sys.exit(1)


if __name__ == '__main__':
    main()