#!/usr/bin/env python3
"""
Test script to check pending jobs
"""

import sys
import os

# Add the crawlers directory to Python path
sys.path.append(os.path.join(os.path.dirname(os.path.abspath(__file__)), 'crawlers'))

import mysql.connector
from crawlers.config import DATABASE_CONFIG

def main():
    """Check pending jobs"""
    try:
        conn = mysql.connector.connect(**DATABASE_CONFIG)
        cursor = conn.cursor(dictionary=True)
        
        # Check total pending jobs
        cursor.execute("SELECT COUNT(*) as count FROM crawl_jobs WHERE status = 'pending'")
        result = cursor.fetchone()
        print(f"Total pending jobs: {result['count']}")
        
        # Check pending jobs ready to process (scheduled_at <= NOW())
        cursor.execute("SELECT COUNT(*) as count FROM crawl_jobs WHERE status = 'pending' AND scheduled_at <= NOW()")
        result = cursor.fetchone()
        print(f"Pending jobs ready to process: {result['count']}")
        
        # Get a sample pending job
        cursor.execute("""
            SELECT id, url, status, scheduled_at, NOW() as current_time
            FROM crawl_jobs 
            WHERE status = 'pending' 
            ORDER BY scheduled_at ASC 
            LIMIT 1
        """)
        job = cursor.fetchone()
        if job:
            print(f"\nSample job:")
            print(f"  ID: {job['id']}")
            print(f"  URL: {job['url']}")
            print(f"  Status: {job['status']}")
            print(f"  Scheduled at: {job['scheduled_at']}")
            print(f"  Current time: {job['current_time']}")
            print(f"  Is ready: {job['scheduled_at'] <= job['current_time']}")
        
        cursor.close()
        conn.close()
        
    except Exception as e:
        print(f"Error: {e}")
        return 1
    
    return 0

if __name__ == '__main__':
    sys.exit(main())
