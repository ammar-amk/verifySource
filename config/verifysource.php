<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Web Scraper Settings
    |--------------------------------------------------------------------------
    */
    'scraper' => [
        'timeout' => env('SCRAPER_TIMEOUT', 30),
        'retries' => env('SCRAPER_RETRIES', 3),
        'delay_between_requests' => env('SCRAPER_DELAY', 1), // seconds
        'max_content_length' => env('SCRAPER_MAX_CONTENT', 10485760), // 10MB
        'respect_robots_txt' => env('SCRAPER_RESPECT_ROBOTS', true),
        'user_agents' => [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Crawl Job Settings
    |--------------------------------------------------------------------------
    */
    'crawl_jobs' => [
        'max_retries' => env('CRAWL_MAX_RETRIES', 3),
        'default_priority' => env('CRAWL_DEFAULT_PRIORITY', 0),
        'cleanup_days' => env('CRAWL_CLEANUP_DAYS', 30),
        'batch_size' => env('CRAWL_BATCH_SIZE', 50),
        'concurrent_jobs' => env('CRAWL_CONCURRENT_JOBS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Processing Settings
    |--------------------------------------------------------------------------
    */
    'content' => [
        'min_content_length' => env('CONTENT_MIN_LENGTH', 100),
        'max_content_length' => env('CONTENT_MAX_LENGTH', 1048576), // 1MB
        'excerpt_length' => env('CONTENT_EXCERPT_LENGTH', 160),
        'duplicate_threshold' => env('CONTENT_DUPLICATE_THRESHOLD', 0.8),
        'quality_threshold' => env('CONTENT_QUALITY_THRESHOLD', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | Python Crawler Settings
    |--------------------------------------------------------------------------
    */
    'python' => [
        'executable' => env('PYTHON_EXECUTABLE', 'python3'),
        'enabled' => env('PYTHON_CRAWLER_ENABLED', true),
        'timeout' => env('PYTHON_CRAWLER_TIMEOUT', 300),
        'fallback_to_php' => env('PYTHON_FALLBACK_TO_PHP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Crawler Settings (for backward compatibility)
    |--------------------------------------------------------------------------
    */
    'crawler' => [
        'max_pages_per_domain' => env('CRAWLER_MAX_PAGES_PER_DOMAIN', 100),
        'delay_between_requests' => env('CRAWLER_DELAY_BETWEEN_REQUESTS', 1),
        'user_agent' => env('CRAWLER_USER_AGENT', 'VerifySource Bot 1.0'),
        'timeout' => 30,
        'max_retries' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Engine Settings
    |--------------------------------------------------------------------------
    */
    'search' => [
        'enabled' => env('SEARCH_ENABLED', true),
        'default_engine' => env('SEARCH_DEFAULT_ENGINE', 'hybrid'), // 'meilisearch', 'qdrant', 'hybrid'
        
        // Meilisearch Configuration (Full-text Search)
        'meilisearch' => [
            'host' => env('MEILISEARCH_HOST', 'http://127.0.0.1:7700'),
            'key' => env('MEILISEARCH_KEY', ''),
            'index_prefix' => env('MEILISEARCH_INDEX_PREFIX', 'verifysource_'),
            'timeout' => env('MEILISEARCH_TIMEOUT', 30),
            'indices' => [
                'articles' => [
                    'primary_key' => 'id',
                    'searchable_attributes' => ['title', 'content', 'excerpt', 'authors'],
                    'filterable_attributes' => ['source_id', 'published_at', 'language', 'quality_score'],
                    'sortable_attributes' => ['published_at', 'quality_score', 'created_at'],
                    'ranking_rules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
                    'stop_words' => ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'],
                    'synonyms' => [],
                ],
                'sources' => [
                    'primary_key' => 'id',
                    'searchable_attributes' => ['name', 'domain', 'description'],
                    'filterable_attributes' => ['is_active', 'credibility_score'],
                    'sortable_attributes' => ['credibility_score', 'created_at'],
                ]
            ]
        ],
        
        // Qdrant Configuration (Semantic/Vector Search)
        'qdrant' => [
            'host' => env('QDRANT_HOST', 'http://127.0.0.1:6333'),
            'api_key' => env('QDRANT_API_KEY', ''),
            'timeout' => env('QDRANT_TIMEOUT', 30),
            'collections' => [
                'articles' => [
                    'name' => env('QDRANT_ARTICLES_COLLECTION', 'verifysource_articles'),
                    'vector_size' => env('QDRANT_VECTOR_SIZE', 768), // Default for sentence-transformers
                    'distance' => env('QDRANT_DISTANCE_METRIC', 'Cosine'), // Cosine, Euclidean, Dot
                    'on_disk_payload' => env('QDRANT_ON_DISK_PAYLOAD', true),
                    'hnsw_config' => [
                        'm' => env('QDRANT_HNSW_M', 16),
                        'ef_construct' => env('QDRANT_HNSW_EF_CONSTRUCT', 100),
                    ]
                ]
            ]
        ],
        
        // Search Configuration
        'options' => [
            'default_limit' => env('SEARCH_DEFAULT_LIMIT', 20),
            'max_limit' => env('SEARCH_MAX_LIMIT', 100),
            'similarity_threshold' => env('SEARCH_SIMILARITY_THRESHOLD', 0.7),
            'hybrid_weights' => [
                'meilisearch' => env('SEARCH_MEILISEARCH_WEIGHT', 0.6),
                'qdrant' => env('SEARCH_QDRANT_WEIGHT', 0.4),
            ],
        ],
        
        // Embedding Models Configuration
        'embeddings' => [
            'enabled' => env('EMBEDDINGS_ENABLED', true),
            'model' => env('EMBEDDINGS_MODEL', 'all-MiniLM-L6-v2'), // sentence-transformers model
            'max_tokens' => env('EMBEDDINGS_MAX_TOKENS', 512),
            'batch_size' => env('EMBEDDINGS_BATCH_SIZE', 32),
            'cache_embeddings' => env('EMBEDDINGS_CACHE', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Verification Settings
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'enabled' => env('VERIFICATION_ENABLED', true),
        'auto_verify' => env('AUTO_VERIFY_CONTENT', true),
        'similarity_threshold' => env('VERIFICATION_SIMILARITY_THRESHOLD', 0.8),
        'credibility_threshold' => env('VERIFICATION_CREDIBILITY_THRESHOLD', 0.5),
        'max_results' => env('VERIFICATION_MAX_RESULTS', 10),
        'cache_duration' => env('VERIFICATION_CACHE_DURATION', 3600), // seconds
        'batch_size' => env('VERIFICATION_BATCH_SIZE', 50),
        
        // Confidence scoring thresholds
        'confidence_levels' => [
            'high' => 0.8,      // High confidence in verification result
            'medium' => 0.6,    // Medium confidence
            'low' => 0.4,       // Low confidence - manual review recommended
        ],
        
        // Evidence requirements for different confidence levels
        'evidence_requirements' => [
            'timestamp_verification' => true,  // Require timestamp checks
            'source_verification' => true,     // Verify source credibility
            'content_matching' => true,        // Require content similarity analysis
            'provenance_tracking' => true,     // Track content origin
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | External APIs Configuration
    |--------------------------------------------------------------------------
    */
    'apis' => [
        'wayback_machine' => [
            'enabled' => env('WAYBACK_MACHINE_ENABLED', true),
            'base_url' => env('WAYBACK_MACHINE_API_URL', 'https://web.archive.org'),
            'cdx_api' => '/cdx/search/cdx',
            'availability_api' => '/wayback/available',
            'timeout' => env('WAYBACK_MACHINE_TIMEOUT', 15),
            'rate_limit' => env('WAYBACK_MACHINE_RATE_LIMIT', 60), // requests per minute
            'user_agent' => env('WAYBACK_MACHINE_USER_AGENT', 'VerifySource/1.0 (Content Verification Bot)'),
        ],
        
        'duckduckgo' => [
            'enabled' => env('DUCKDUCKGO_ENABLED', false),
            'base_url' => env('DUCKDUCKGO_API_URL', 'https://api.duckduckgo.com'),
            'timeout' => env('DUCKDUCKGO_TIMEOUT', 10),
        ],
        
        // Additional APIs for verification
        'google_search' => [
            'enabled' => env('GOOGLE_SEARCH_ENABLED', false),
            'api_key' => env('GOOGLE_SEARCH_API_KEY', ''),
            'search_engine_id' => env('GOOGLE_SEARCH_ENGINE_ID', ''),
            'timeout' => env('GOOGLE_SEARCH_TIMEOUT', 10),
        ],
        
        'domain_tools' => [
            'enabled' => env('DOMAIN_TOOLS_ENABLED', false),
            'api_key' => env('DOMAIN_TOOLS_API_KEY', ''),
            'timeout' => env('DOMAIN_TOOLS_TIMEOUT', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Credibility and Provenance Settings
    |--------------------------------------------------------------------------
    */
    'provenance' => [
        'max_search_depth' => env('PROVENANCE_MAX_SEARCH_DEPTH', 10),
        'time_window_days' => env('PROVENANCE_TIME_WINDOW_DAYS', 30),
        'minimum_publication_gap' => env('PROVENANCE_MIN_PUB_GAP_HOURS', 1), // hours
        'content_similarity_threshold' => env('PROVENANCE_SIMILARITY_THRESHOLD', 0.85),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring and Credibility Algorithms
    |--------------------------------------------------------------------------
    */
    'credibility' => [
        'algorithm' => env('CREDIBILITY_ALGORITHM', 'weighted'), // 'weighted', 'neural', 'composite'
        
        // Domain credibility factors
        'domain_factors' => [
            'domain_age' => 0.15,           // How long the domain has existed
            'ssl_certificate' => 0.10,      // HTTPS and valid certificates
            'website_structure' => 0.15,    // Professional layout and navigation
            'contact_information' => 0.10,  // About page, contact details
            'social_media_presence' => 0.10, // Official social accounts
            'editorial_standards' => 0.20,  // Corrections policy, author bios
            'fact_checking_history' => 0.20, // Historical accuracy of content
        ],
        
        // Content credibility factors
        'content_factors' => [
            'author_credibility' => 0.25,   // Author expertise and reputation
            'source_citations' => 0.20,     // Links to primary sources
            'publication_freshness' => 0.15, // How recent the content is
            'content_depth' => 0.15,        // Length and detail of content
            'factual_accuracy' => 0.25,     // Cross-verification with known facts
        ],
        
        // Overall credibility weights
        'overall_weights' => [
            'domain_credibility' => 0.4,
            'content_credibility' => 0.3,
            'verification_confidence' => 0.2,
            'community_feedback' => 0.1,
        ],
    ],
    
    'scoring' => [
        // Legacy similarity weights (keeping for backward compatibility)
        'similarity_weights' => [
            'exact_match' => 1.0,
            'semantic_similarity' => 0.8,
            'partial_match' => 0.6,
        ],
        
        // Verification scoring weights
        'verification_weights' => [
            'timestamp_accuracy' => 0.3,    // Accuracy of publication timestamps
            'content_originality' => 0.25,  // How original vs copied the content is
            'source_reliability' => 0.25,   // Reliability of the publishing source
            'cross_verification' => 0.20,   // Verification across multiple sources
        ],
        
        // Provenance scoring
        'provenance_weights' => [
            'earliest_publication' => 0.4,  // Weight for being the earliest source
            'authority_boost' => 0.3,       // Boost for authoritative sources
            'citation_network' => 0.2,      // How well the content is cited
            'update_history' => 0.1,        // Content update and correction history
        ],
    ],
];
