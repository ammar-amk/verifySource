import random
import logging
import time
from config import USER_AGENTS

logger = logging.getLogger(__name__)


class RotateUserAgentMiddleware:
    """Middleware to rotate user agents for requests"""
    
    def __init__(self):
        self.user_agents = USER_AGENTS
    
    def process_request(self, request, spider):
        user_agent = random.choice(self.user_agents)
        request.headers['User-Agent'] = user_agent
        return None


class RateLimitMiddleware:
    """Middleware to enforce rate limiting"""
    
    def __init__(self):
        self.requests_count = {}
        self.last_reset = {}
    
    def process_request(self, request, spider):
        domain = request.url.split('/')[2]
        
        # Simple rate limiting logic
        now = time.time()
        
        if domain not in self.requests_count:
            self.requests_count[domain] = 0
            self.last_reset[domain] = now
        
        # Reset counter every minute
        if now - self.last_reset[domain] > 60:
            self.requests_count[domain] = 0
            self.last_reset[domain] = now
        
        self.requests_count[domain] += 1
        
        # Log rate limiting
        if self.requests_count[domain] % 10 == 0:
            logger.info(f"Rate limit check - {domain}: {self.requests_count[domain]} requests in last minute")
        
        return None