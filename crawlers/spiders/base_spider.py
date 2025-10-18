import scrapy
from scrapy import Request
from urllib.parse import urljoin, urlparse
import re
import logging
from datetime import datetime

logger = logging.getLogger(__name__)


class BaseSpider(scrapy.Spider):
    """Base spider class for VerifySource crawlers"""
    
    name = 'base_spider'
    allowed_domains = []
    start_urls = []
    
    # Custom settings
    custom_settings = {
        'ROBOTSTXT_OBEY': True,
        'DOWNLOAD_DELAY': 1,
        'CONCURRENT_REQUESTS_PER_DOMAIN': 2,
    }
    
    def __init__(self, *args, **kwargs):
        super(BaseSpider, self).__init__(*args, **kwargs)
        self.source_id = kwargs.get('source_id')
        self.crawl_job_id = kwargs.get('crawl_job_id')
        self.base_url = kwargs.get('base_url')
        
        if self.base_url:
            parsed = urlparse(self.base_url)
            self.allowed_domains = [parsed.netloc]
            
        logger.info(f"Initialized {self.name} spider for source {self.source_id}")
    
    def start_requests(self):
        """Generate initial requests"""
        for url in self.start_urls:
            yield Request(
                url=url,
                callback=self.parse,
                meta={
                    'source_id': self.source_id,
                    'crawl_job_id': self.crawl_job_id,
                    'depth': 0
                }
            )
    
    def parse(self, response):
        """Default parse method - should be overridden"""
        logger.info(f"Parsing {response.url}")
        
        # Extract article content using Newspaper3k
        from crawlers.extractors.newspaper_extractor import NewspaperExtractor
        
        extractor = NewspaperExtractor()
        article_data = extractor.extract_from_response(response)
        
        if article_data:
            article_data.update({
                'source_id': response.meta.get('source_id'),
                'crawl_job_id': response.meta.get('crawl_job_id'),
                'scraped_at': datetime.utcnow().isoformat(),
                'spider_name': self.name
            })
            
            yield article_data
        
        # Extract and follow links
        if response.meta.get('depth', 0) < self.max_depth:
            for link in self.extract_links(response):
                yield Request(
                    url=link,
                    callback=self.parse,
                    meta={
                        **response.meta,
                        'depth': response.meta.get('depth', 0) + 1
                    }
                )
    
    def extract_links(self, response):
        """Extract relevant links from the page"""
        links = []
        
        # Get all links from the page
        for link in response.css('a::attr(href)').getall():
            absolute_link = urljoin(response.url, link)
            
            # Filter links
            if self.is_valid_link(absolute_link, response.url):
                links.append(absolute_link)
        
        return links[:self.max_links_per_page]  # Limit links per page
    
    def is_valid_link(self, link, base_url):
        """Check if a link should be followed"""
        parsed_link = urlparse(link)
        parsed_base = urlparse(base_url)
        
        # Must be same domain
        if parsed_link.netloc != parsed_base.netloc:
            return False
        
        # Skip non-HTTP protocols
        if parsed_link.scheme not in ['http', 'https']:
            return False
        
        # Skip common non-article paths
        skip_patterns = [
            r'/tag/', r'/category/', r'/author/', r'/search/',
            r'/contact', r'/about', r'/privacy', r'/terms',
            r'\.pdf$', r'\.jpg$', r'\.png$', r'\.gif$',
            r'/feed', r'/rss', r'/sitemap'
        ]
        
        for pattern in skip_patterns:
            if re.search(pattern, link, re.IGNORECASE):
                return False
        
        # Look for article-like patterns
        article_patterns = [
            r'/\d{4}/\d{2}/\d{2}/',  # Date in URL
            r'/article/', r'/post/', r'/news/', r'/story/',
            r'/blog/', r'/press/', r'/release/'
        ]
        
        for pattern in article_patterns:
            if re.search(pattern, link, re.IGNORECASE):
                return True
        
        # If URL has meaningful path segments, include it
        path_segments = [seg for seg in parsed_link.path.split('/') if seg]
        if len(path_segments) >= 2:
            return True
        
        return False
    
    @property
    def max_depth(self):
        """Maximum crawl depth"""
        return getattr(self, '_max_depth', 2)
    
    @property
    def max_links_per_page(self):
        """Maximum links to follow per page"""
        return getattr(self, '_max_links_per_page', 50)


class NewsSpider(BaseSpider):
    """Spider specifically for news websites"""
    
    name = 'news_spider'
    
    custom_settings = {
        **BaseSpider.custom_settings,
        'DOWNLOAD_DELAY': 2,  # Be more respectful to news sites
    }
    
    def __init__(self, *args, **kwargs):
        super(NewsSpider, self).__init__(*args, **kwargs)
        self._max_depth = 3
        self._max_links_per_page = 100
    
    def is_valid_link(self, link, base_url):
        """Enhanced link validation for news sites"""
        if not super().is_valid_link(link, base_url):
            return False
        
        # News-specific patterns
        news_patterns = [
            r'/\d{4}/\d{2}/\d{2}/',  # Date-based URLs
            r'/breaking/', r'/latest/', r'/headlines/',
            r'/politics/', r'/business/', r'/technology/',
            r'/sports/', r'/entertainment/', r'/health/'
        ]
        
        for pattern in news_patterns:
            if re.search(pattern, link, re.IGNORECASE):
                return True
        
        return False


class BlogSpider(BaseSpider):
    """Spider for blog websites"""
    
    name = 'blog_spider'
    
    def __init__(self, *args, **kwargs):
        super(BlogSpider, self).__init__(*args, **kwargs)
        self._max_depth = 2
        self._max_links_per_page = 30
    
    def is_valid_link(self, link, base_url):
        """Enhanced link validation for blogs"""
        if not super().is_valid_link(link, base_url):
            return False
        
        # Blog-specific patterns
        blog_patterns = [
            r'/blog/', r'/post/', r'/\d{4}/\d{2}/',
            r'/entry/', r'/archive/'
        ]
        
        for pattern in blog_patterns:
            if re.search(pattern, link, re.IGNORECASE):
                return True
        
        return False


class SinglePageSpider(BaseSpider):
    """Spider for crawling single URLs without following links"""
    
    name = 'single_page_spider'
    
    def __init__(self, *args, **kwargs):
        super(SinglePageSpider, self).__init__(*args, **kwargs)
        self._max_depth = 0  # Don't follow links
        self._max_links_per_page = 0
    
    def extract_links(self, response):
        """Don't extract links for single page crawling"""
        return []


class SitemapSpider(scrapy.Spider):
    """Spider for processing XML sitemaps"""
    
    name = 'sitemap_spider'
    
    def __init__(self, sitemap_url=None, source_id=None, *args, **kwargs):
        super(SitemapSpider, self).__init__(*args, **kwargs)
        self.sitemap_url = sitemap_url
        self.source_id = source_id
        self.start_urls = [sitemap_url] if sitemap_url else []
    
    def parse(self, response):
        """Parse sitemap and extract URLs"""
        logger.info(f"Processing sitemap: {response.url}")
        
        # Handle XML sitemaps
        if 'xml' in response.headers.get('Content-Type', b'').decode():
            urls = response.xpath('//url/loc/text()').getall()
            if not urls:  # Try sitemap index
                urls = response.xpath('//sitemap/loc/text()').getall()
                # Process nested sitemaps
                for url in urls[:10]:  # Limit nested sitemaps
                    yield Request(url=url, callback=self.parse)
                return
        else:
            # Handle HTML sitemaps
            urls = response.css('a::attr(href)').getall()
        
        logger.info(f"Found {len(urls)} URLs in sitemap")
        
        # Return URLs for processing
        for url in urls:
            absolute_url = urljoin(response.url, url)
            yield {
                'url': absolute_url,
                'source_id': self.source_id,
                'found_in_sitemap': True,
                'sitemap_url': response.url,
                'discovered_at': datetime.utcnow().isoformat()
            }