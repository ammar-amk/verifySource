import logging
from newspaper import Article, Config
from newspaper.article import ArticleException
from urllib.parse import urljoin, urlparse
from datetime import datetime
import re
import hashlib
from config import NEWSPAPER_CONFIG

logger = logging.getLogger(__name__)


class NewspaperExtractor:
    """Content extractor using Newspaper3k library"""
    
    def __init__(self):
        self.config = Config()
        self.config.browser_user_agent = NEWSPAPER_CONFIG['browser_user_agent']
        self.config.request_timeout = NEWSPAPER_CONFIG['request_timeout']
        self.config.number_threads = NEWSPAPER_CONFIG['number_threads']
        self.config.verbose = NEWSPAPER_CONFIG['verbose']
        self.config.memoize_articles = NEWSPAPER_CONFIG['memoize_articles']
        self.config.fetch_images = NEWSPAPER_CONFIG['fetch_images']
        self.config.keep_article_html = NEWSPAPER_CONFIG['keep_article_html']
        self.config.http_success_only = NEWSPAPER_CONFIG['http_success_only']
    
    def extract_from_url(self, url):
        """Extract article content from URL"""
        try:
            logger.info(f"Extracting content from URL: {url}")
            
            article = Article(url, config=self.config)
            article.download()
            article.parse()
            article.nlp()  # Apply NLP processing
            
            return self._process_article(article, url)
            
        except ArticleException as e:
            logger.error(f"Article extraction failed for {url}: {e}")
            return None
        except Exception as e:
            logger.error(f"Unexpected error extracting {url}: {e}")
            return None
    
    def extract_from_response(self, response):
        """Extract article content from Scrapy response"""
        try:
            logger.info(f"Extracting content from response: {response.url}")
            
            article = Article(response.url, config=self.config)
            article.set_html(response.text)
            article.parse()
            article.nlp()  # Apply NLP processing
            
            return self._process_article(article, response.url)
            
        except ArticleException as e:
            logger.error(f"Article extraction failed for {response.url}: {e}")
            return None
        except Exception as e:
            logger.error(f"Unexpected error extracting {response.url}: {e}")
            return None
    
    def _process_article(self, article, url):
        """Process extracted article and return structured data"""
        
        # Skip if no meaningful content
        if not article.text or len(article.text.strip()) < 100:
            logger.info(f"Skipping article with insufficient content: {url}")
            return None
        
        # Extract and clean data
        extracted_data = {
            'url': self._normalize_url(url),
            'title': self._clean_title(article.title),
            'content': self._clean_content(article.text),
            'excerpt': self._generate_excerpt(article.text),
            'authors': self._extract_authors(article.authors),
            'published_at': self._parse_publish_date(article.publish_date),
            'top_image': article.top_image,
            'images': list(article.images)[:5],  # Limit to 5 images
            'videos': list(article.movies),
            'keywords': list(article.keywords)[:10],  # Limit to 10 keywords
            'summary': article.summary,
            'meta_description': article.meta_description,
            'meta_keywords': article.meta_keywords,
            'canonical_link': article.canonical_link,
            'language': article.meta_lang or self._detect_language(article.text),
            'source_url': article.source_url,
            'content_hash': self._generate_content_hash(article.text),
            'word_count': len(article.text.split()),
            'html': article.html if hasattr(article, 'html') else None,
        }
        
        # Add quality metrics
        extracted_data.update(self._calculate_quality_metrics(extracted_data))
        
        logger.info(f"Successfully extracted article: {extracted_data['title'][:50]}...")
        
        return extracted_data
    
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
    
    def _clean_title(self, title):
        """Clean and normalize article title"""
        if not title:
            return None
        
        # Remove site name suffixes
        title = re.sub(r'\s*[-|–—]\s*[^-|–—]*$', '', title)
        
        # Clean whitespace
        title = re.sub(r'\s+', ' ', title).strip()
        
        # Remove excessive punctuation
        title = re.sub(r'[!]{2,}', '!', title)
        title = re.sub(r'[?]{2,}', '?', title)
        
        return title if len(title) > 5 else None
    
    def _clean_content(self, content):
        """Clean article content"""
        if not content:
            return None
        
        # Remove excessive whitespace
        content = re.sub(r'\s+', ' ', content)
        content = re.sub(r'\n{3,}', '\n\n', content)
        
        # Remove common boilerplate
        boilerplate_patterns = [
            r'Subscribe to.*?newsletter',
            r'Follow us on.*?social media',
            r'Click here to.*?subscribe',
            r'Advertisement',
            r'Sponsored content',
            r'Read more:',
            r'Continue reading',
        ]
        
        for pattern in boilerplate_patterns:
            content = re.sub(pattern, '', content, flags=re.IGNORECASE)
        
        return content.strip()
    
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
    
    def _extract_authors(self, authors):
        """Extract and clean author information"""
        if not authors:
            return None
        
        # Join multiple authors
        if isinstance(authors, list):
            clean_authors = []
            for author in authors:
                if author and isinstance(author, str):
                    clean_author = re.sub(r'^(by|author:?)\s+', '', author, flags=re.IGNORECASE)
                    clean_author = re.sub(r'\s+', ' ', clean_author).strip()
                    if clean_author and len(clean_author) > 2:
                        clean_authors.append(clean_author)
            
            return ', '.join(clean_authors[:3]) if clean_authors else None  # Max 3 authors
        
        return str(authors) if authors else None
    
    def _parse_publish_date(self, publish_date):
        """Parse and format publish date"""
        if not publish_date:
            return None
        
        try:
            if hasattr(publish_date, 'isoformat'):
                return publish_date.isoformat()
            else:
                return str(publish_date)
        except Exception as e:
            logger.warning(f"Failed to parse publish date: {publish_date}, error: {e}")
            return None
    
    def _detect_language(self, text):
        """Simple language detection"""
        if not text or len(text) < 50:
            return 'en'  # Default to English
        
        # Simple keyword-based detection
        text_lower = text.lower()
        
        language_indicators = {
            'en': ['the', 'and', 'that', 'have', 'for', 'not', 'with', 'you', 'this', 'but'],
            'es': ['que', 'de', 'no', 'la', 'el', 'en', 'un', 'es', 'se', 'le'],
            'fr': ['que', 'de', 'je', 'est', 'pas', 'le', 'vous', 'la', 'tu', 'il'],
            'de': ['der', 'die', 'und', 'in', 'den', 'von', 'zu', 'das', 'mit', 'sich'],
            'it': ['che', 'di', 'da', 'in', 'un', 'il', 'del', 'non', 'sono', 'una'],
        }
        
        scores = {}
        for lang, keywords in language_indicators.items():
            score = sum(text_lower.count(f' {keyword} ') for keyword in keywords)
            scores[lang] = score
        
        detected_lang = max(scores, key=scores.get) if scores else 'en'
        return detected_lang if scores[detected_lang] > 0 else 'en'
    
    def _generate_content_hash(self, content):
        """Generate hash for content deduplication"""
        if not content:
            return None
        
        # Normalize content for hashing
        normalized = re.sub(r'\s+', ' ', content.lower().strip())
        normalized = re.sub(r'[^\w\s]', '', normalized)  # Remove punctuation
        
        return hashlib.sha256(normalized.encode('utf-8')).hexdigest()
    
    def _calculate_quality_metrics(self, data):
        """Calculate content quality metrics"""
        quality_score = 0
        quality_factors = []
        quality_issues = []
        
        # Title quality
        if data.get('title'):
            title_length = len(data['title'])
            if 10 <= title_length <= 200:
                quality_score += 20
                quality_factors.append('Good title length')
            else:
                quality_issues.append('Title length issues')
        else:
            quality_issues.append('Missing title')
        
        # Content quality
        if data.get('content'):
            content_length = len(data['content'])
            word_count = data.get('word_count', 0)
            
            if content_length >= 500:
                quality_score += 25
                quality_factors.append('Substantial content')
            elif content_length >= 200:
                quality_score += 15
                quality_factors.append('Moderate content')
            else:
                quality_issues.append('Short content')
            
            if word_count >= 100:
                quality_score += 10
                quality_factors.append('Good word count')
        else:
            quality_issues.append('Missing content')
        
        # Author information
        if data.get('authors'):
            quality_score += 15
            quality_factors.append('Author information')
        
        # Publication date
        if data.get('published_at'):
            quality_score += 10
            quality_factors.append('Publication date')
        
        # Meta information
        if data.get('meta_description'):
            quality_score += 5
            quality_factors.append('Meta description')
        
        if data.get('keywords'):
            quality_score += 5
            quality_factors.append('Keywords extracted')
        
        # Images
        if data.get('top_image'):
            quality_score += 5
            quality_factors.append('Featured image')
        
        # Summary
        if data.get('summary'):
            quality_score += 5
            quality_factors.append('Auto-generated summary')
        
        return {
            'quality_score': min(quality_score, 100),
            'quality_factors': quality_factors,
            'quality_issues': quality_issues,
        }


class ContentValidator:
    """Validates extracted content quality"""
    
    @staticmethod
    def is_valid_article(data):
        """Check if extracted data represents a valid article"""
        
        # Must have title and content
        if not data.get('title') or not data.get('content'):
            return False
        
        # Content must be substantial
        if len(data['content']) < 100:
            return False
        
        # Title must be reasonable
        title_length = len(data['title'])
        if title_length < 10 or title_length > 500:
            return False
        
        # Check for spam indicators
        content_lower = data['content'].lower()
        spam_indicators = [
            'buy now', 'click here', 'limited time', 'act now',
            'free trial', 'make money', 'work from home'
        ]
        
        spam_count = sum(1 for indicator in spam_indicators if indicator in content_lower)
        if spam_count >= 3:  # Too many spam indicators
            return False
        
        return True
    
    @staticmethod
    def calculate_readability_score(content):
        """Calculate simple readability score"""
        if not content:
            return 0
        
        sentences = len(re.findall(r'[.!?]+', content))
        words = len(content.split())
        
        if sentences == 0 or words == 0:
            return 0
        
        avg_sentence_length = words / sentences
        
        # Simple readability score (inverse of average sentence length)
        readability = max(0, 100 - avg_sentence_length * 2)
        
        return min(readability, 100)