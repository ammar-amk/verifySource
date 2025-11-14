#!/usr/bin/env python3
"""
Standalone URL Content Extractor for VerifySource
A completely self-contained crawler without external dependencies that cause conflicts
"""

import sys
import os
import argparse
import logging
import json
import re
import hashlib
from datetime import datetime
from urllib.parse import urlparse, urljoin
import urllib.request
import urllib.error
from html.parser import HTMLParser

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s'
)
logger = logging.getLogger(__name__)


class SimpleHTMLParser(HTMLParser):
    """Simple HTML parser to extract text content"""
    
    def __init__(self):
        super().__init__()
        self.text_parts = []
        self.in_title = False
        self.in_meta = False
        self.title = ""
        self.meta_description = ""
        self.meta_keywords = ""
        self.current_tag = ""
        self.current_attrs = {}
        
    def handle_starttag(self, tag, attrs):
        self.current_tag = tag.lower()
        self.current_attrs = dict(attrs)
        
        if tag.lower() == 'title':
            self.in_title = True
        elif tag.lower() == 'meta':
            self.in_meta = True
            
    def handle_endtag(self, tag):
        if tag.lower() == 'title':
            self.in_title = False
        elif tag.lower() == 'meta':
            self.in_meta = False
            
    def handle_data(self, data):
        if self.in_title:
            self.title += data
        elif self.in_meta:
            if self.current_attrs.get('name') == 'description':
                self.meta_description = self.current_attrs.get('content', '')
            elif self.current_attrs.get('name') == 'keywords':
                self.meta_keywords = self.current_attrs.get('content', '')
        else:
            # Collect text from all content tags, including body
            if self.current_tag in ['p', 'div', 'article', 'section', 'main', 'body', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'li']:
                if data.strip():  # Only add non-empty text
                    self.text_parts.append(data.strip())
                
    def get_text(self):
        """Get cleaned text content"""
        full_text = ' '.join(self.text_parts)
        # Clean up whitespace
        full_text = re.sub(r'\s+', ' ', full_text).strip()
        return full_text


class StandaloneURLExtractor:
    """Standalone URL content extractor using only standard library"""
    
    def __init__(self):
        self.user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        
    def extract_url(self, url, source_id=None, crawl_job_id=None):
        """Extract content from a single URL"""
        logger.info(f"Extracting content from URL: {url}")
        
        try:
            # Fetch the URL
            content = self._fetch_url(url)
            if not content:
                return None
                
            # Parse HTML
            parser = SimpleHTMLParser()
            parser.feed(content)
            
            # Extract basic information
            title = parser.title.strip() if parser.title else None
            text_content = parser.get_text()
            meta_description = parser.meta_description
            meta_keywords = parser.meta_keywords
            
            # Fallback: if no text extracted, try simple regex extraction
            if not text_content or len(text_content.strip()) < 10:
                logger.debug("Structured parsing failed, trying regex fallback")
                # Remove HTML tags and extract text
                text_content = re.sub(r'<[^>]+>', ' ', content)
                text_content = re.sub(r'\s+', ' ', text_content).strip()
            
            # Skip if no meaningful content
            if not text_content or len(text_content.strip()) < 100:
                logger.warning(f"Insufficient content extracted from URL: {url}")
                return None
            
            # Generate excerpt
            excerpt = self._generate_excerpt(text_content)
            
            # Extract keywords (simple approach)
            keywords = self._extract_keywords(text_content)
            
            # Generate content hash
            content_hash = self._generate_content_hash(text_content)
            
            # Calculate word count
            word_count = len(text_content.split())
            
            # Build result
            result = {
                'url': self._normalize_url(url),
                'title': title,
                'content': text_content,
                'excerpt': excerpt,
                'authors': None,  # Would need more sophisticated parsing
                'published_at': None,  # Would need more sophisticated parsing
                'top_image': None,  # Would need more sophisticated parsing
                'images': [],
                'videos': [],
                'keywords': keywords,
                'summary': excerpt,  # Use excerpt as summary
                'meta_description': meta_description,
                'meta_keywords': [meta_keywords] if meta_keywords else [],
                'canonical_link': None,
                'language': 'en',  # Default to English
                'source_url': url,
                'content_hash': content_hash,
                'word_count': word_count,
                'html': content,
                'quality_score': self._calculate_quality_score(title, text_content, word_count),
                'quality_factors': self._get_quality_factors(title, text_content, word_count),
                'quality_issues': self._get_quality_issues(title, text_content, word_count),
                'source_id': source_id,
                'crawl_job_id': crawl_job_id,
                'scraped_at': datetime.now().isoformat(),
                'extraction_method': 'standalone_parser'
            }
            
            logger.info(f"Successfully extracted article: {title[:50] if title else 'No title'}")
            return result
            
        except Exception as e:
            error_msg = str(e)
            if 'getaddrinfo failed' in error_msg:
                error_msg = f"DNS resolution failed - unable to reach {urlparse(url).netloc}. This could be due to network connectivity issues or the domain being blocked."
            elif 'timeout' in error_msg.lower():
                error_msg = f"Request timeout - the server took too long to respond. The website might be slow or overloaded."
            elif '403' in error_msg or 'forbidden' in error_msg.lower():
                error_msg = f"Access forbidden - the website is blocking requests from this location or user agent."
            elif '404' in error_msg or 'not found' in error_msg.lower():
                error_msg = f"Page not found - the URL might be incorrect or the page has been removed."
            
            logger.error(f"Error extracting content from {url}: {error_msg}")
            return {
                'url': url,
                'title': None,
                'content': None,
                'error': error_msg,
                'extraction_method': 'standalone_parser_failed'
            }
    
    def _fetch_url(self, url):
        """Fetch URL content using urllib with DNS fallback"""
        try:
            req = urllib.request.Request(
                url,
                headers={'User-Agent': self.user_agent}
            )
            
            # Try with IPv4 preference first
            with urllib.request.urlopen(req, timeout=30) as response:
                content = response.read()
                
            # Try to decode as UTF-8, fallback to latin-1
            try:
                return content.decode('utf-8')
            except UnicodeDecodeError:
                return content.decode('latin-1')
                
        except urllib.error.URLError as e:
            if hasattr(e, 'reason') and 'getaddrinfo failed' in str(e.reason):
                logger.warning(f"DNS resolution failed for {url}, trying with different approach: {e.reason}")
                return self._fetch_url_with_fallback(url)
            elif hasattr(e, 'code'):
                logger.error(f"HTTP error {e.code} for {url}: {e.reason}")
                return None
            else:
                logger.error(f"URL error for {url}: {e}")
                return None
        except Exception as e:
            logger.error(f"Error fetching {url}: {e}")
            return None
    
    def _fetch_url_with_fallback(self, url):
        """Fallback method for DNS resolution issues"""
        try:
            # Try with a different approach - force IPv4
            import socket
            
            # Parse the URL
            parsed = urlparse(url)
            hostname = parsed.hostname
            
            # Try to resolve the hostname manually
            try:
                # Force IPv4 resolution
                ip_address = socket.gethostbyname(hostname)
                logger.info(f"Resolved {hostname} to {ip_address}")
                
                # Replace hostname with IP in URL
                fallback_url = url.replace(hostname, ip_address)
                
                req = urllib.request.Request(
                    fallback_url,
                    headers={
                        'User-Agent': self.user_agent,
                        'Host': hostname  # Keep original hostname in Host header
                    }
                )
                
                with urllib.request.urlopen(req, timeout=30) as response:
                    content = response.read()
                    
                # Try to decode as UTF-8, fallback to latin-1
                try:
                    return content.decode('utf-8')
                except UnicodeDecodeError:
                    return content.decode('latin-1')
                    
            except socket.gaierror as dns_error:
                logger.error(f"Manual DNS resolution also failed for {hostname}: {dns_error}")
                return None
                
        except Exception as e:
            logger.error(f"Fallback method failed for {url}: {e}")
            return None
    
    def _normalize_url(self, url):
        """Normalize URL by removing tracking parameters"""
        parsed = urlparse(url)
        
        # Remove common tracking parameters
        query_params = []
        if parsed.query:
            params = parsed.query.split('&')
            tracking_params = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid']
            
            for param in params:
                param_name = param.split('=')[0]
                if param_name not in tracking_params:
                    query_params.append(param)
        
        # Reconstruct URL
        clean_query = '&'.join(query_params) if query_params else ''
        
        return f"{parsed.scheme}://{parsed.netloc}{parsed.path}" + (f"?{clean_query}" if clean_query else "")
    
    def _generate_excerpt(self, content, length=160):
        """Generate excerpt from content"""
        if not content:
            return None
        
        # Clean content for excerpt
        clean_content = re.sub(r'\s+', ' ', content).strip()
        
        if len(clean_content) <= length:
            return clean_content
        
        # Find good breaking point
        excerpt = clean_content[:length]
        last_sentence = excerpt.rfind('. ')
        last_space = excerpt.rfind(' ')
        
        # Use sentence break if available and reasonable
        if last_sentence > length * 0.7:
            return excerpt[:last_sentence + 1]
        elif last_space > length * 0.8:
            return excerpt[:last_space] + '...'
        else:
            return excerpt + '...'
    
    def _extract_keywords(self, content, max_keywords=10):
        """Extract keywords using simple frequency analysis"""
        if not content:
            return []
        
        # Simple keyword extraction - remove common words and get most frequent
        words = re.findall(r'\b[a-zA-Z]{3,}\b', content.lower())
        
        # Common stop words
        stop_words = {
            'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'had', 'her', 'was', 'one', 'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'its', 'may', 'new', 'now', 'old', 'see', 'two', 'way', 'who', 'boy', 'did', 'man', 'oil', 'sit', 'try', 'use', 'she', 'put', 'end', 'why', 'let', 'ask', 'run', 'own', 'say', 'each', 'which', 'their', 'time', 'will', 'about', 'if', 'up', 'out', 'many', 'then', 'them', 'these', 'so', 'some', 'her', 'would', 'make', 'like', 'into', 'him', 'has', 'more', 'go', 'no', 'way', 'could', 'my', 'than', 'first', 'been', 'call', 'who', 'its', 'now', 'find', 'long', 'down', 'day', 'did', 'get', 'come', 'made', 'may', 'part'
        }
        
        # Count word frequency
        word_count = {}
        for word in words:
            if word not in stop_words and len(word) > 3:
                word_count[word] = word_count.get(word, 0) + 1
        
        # Get top keywords
        keywords = sorted(word_count.items(), key=lambda x: x[1], reverse=True)
        return [word for word, count in keywords[:max_keywords]]
    
    def _generate_content_hash(self, content):
        """Generate hash for content deduplication"""
        if not content:
            return None
        
        # Normalize content for hashing
        normalized = re.sub(r'\s+', ' ', content.lower().strip())
        normalized = re.sub(r'[^\w\s]', '', normalized)  # Remove punctuation
        
        return hashlib.sha256(normalized.encode('utf-8')).hexdigest()
    
    def _calculate_quality_score(self, title, content, word_count):
        """Calculate simple quality score"""
        score = 0
        
        # Title quality
        if title and 10 <= len(title) <= 200:
            score += 20
        
        # Content quality
        if content:
            if len(content) >= 500:
                score += 25
            elif len(content) >= 200:
                score += 15
            
            if word_count >= 100:
                score += 10
        
        return min(score, 100)
    
    def _get_quality_factors(self, title, content, word_count):
        """Get quality factors"""
        factors = []
        
        if title and 10 <= len(title) <= 200:
            factors.append('Good title length')
        
        if content and len(content) >= 200:
            factors.append('Substantial content')
        
        if word_count >= 100:
            factors.append('Good word count')
        
        return factors
    
    def _get_quality_issues(self, title, content, word_count):
        """Get quality issues"""
        issues = []
        
        if not title:
            issues.append('Missing title')
        elif len(title) < 10 or len(title) > 200:
            issues.append('Title length issues')
        
        if not content:
            issues.append('Missing content')
        elif len(content) < 200:
            issues.append('Short content')
        
        return issues


def main():
    """Main CLI function"""
    parser = argparse.ArgumentParser(description='Standalone URL Content Extractor')
    
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
    extractor = StandaloneURLExtractor()
    
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
