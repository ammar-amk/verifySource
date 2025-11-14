<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Credibility Scoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the credibility and scoring system that evaluates
    | source trustworthiness, content quality, and bias detection.
    |
    */

    'enabled' => env('CREDIBILITY_SCORING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Scoring Weights
    |--------------------------------------------------------------------------
    |
    | Weights for different components of the credibility score.
    | All weights should sum to 1.0 (100%).
    |
    */
    'weights' => [
        'domain_trust' => 0.35,      // 35% - Domain reputation and trust metrics
        'content_quality' => 0.25,   // 25% - Content quality and editorial standards
        'bias_assessment' => 0.20,   // 20% - Editorial bias and neutrality
        'external_validation' => 0.15, // 15% - External fact-checking and validation
        'historical_accuracy' => 0.05, // 5% - Historical accuracy track record
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Trust Configuration
    |--------------------------------------------------------------------------
    */
    'domain_trust' => [
        'cache_ttl' => 86400, // 24 hours
        'whois_analysis' => true,
        'ssl_validation' => true,
        'domain_age_threshold' => 365, // days

        'trust_indicators' => [
            'https_enabled' => 10,
            'valid_ssl_certificate' => 15,
            'domain_age_over_threshold' => 10,
            'professional_whois' => 5,
            'no_security_issues' => 20,
        ],

        'risk_factors' => [
            'recently_registered' => -15,
            'invalid_ssl' => -20,
            'suspicious_registrar' => -10,
            'security_warnings' => -25,
            'blacklisted_domain' => -50,
        ],

        'trusted_tlds' => [
            '.gov', '.edu', '.org', '.mil',
            '.gov.uk', '.gov.au', '.gov.ca',
            '.ac.uk', '.edu.au',
        ],

        'suspicious_tlds' => [
            '.tk', '.ml', '.ga', '.cf', '.top',
            '.click', '.download', '.review',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Quality Configuration
    |--------------------------------------------------------------------------
    */
    'content_quality' => [
        'readability' => [
            'flesch_kincaid_weight' => 0.3,
            'gunning_fog_weight' => 0.3,
            'automated_readability_weight' => 0.2,
            'sentence_complexity_weight' => 0.2,
        ],

        'quality_indicators' => [
            'proper_grammar' => 10,
            'appropriate_length' => 5,
            'source_citations' => 15,
            'factual_statements' => 10,
            'balanced_perspective' => 10,
            'clear_structure' => 5,
        ],

        'quality_detractors' => [
            'excessive_ads' => -10,
            'clickbait_language' => -15,
            'poor_grammar' => -10,
            'emotional_manipulation' => -15,
            'unsupported_claims' => -20,
        ],

        'minimum_content_length' => 100,
        'optimal_content_length' => [500, 3000],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bias Detection Configuration
    |--------------------------------------------------------------------------
    */
    'bias_detection' => [
        'sentiment_analysis' => [
            'enabled' => true,
            'extreme_threshold' => 0.8,
            'moderate_threshold' => 0.6,
        ],

        'language_patterns' => [
            'emotional_words_weight' => 0.3,
            'loaded_language_weight' => 0.4,
            'factual_language_weight' => 0.3,
        ],

        'bias_indicators' => [
            'balanced_sources' => 15,
            'multiple_perspectives' => 10,
            'factual_language' => 10,
            'transparent_methodology' => 5,
        ],

        'bias_detractors' => [
            'one_sided_reporting' => -15,
            'emotional_language' => -10,
            'cherry_picking' => -20,
            'misleading_headlines' => -15,
            'propaganda_techniques' => -25,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | External Validation Configuration
    |--------------------------------------------------------------------------
    */
    'external_validation' => [
        'fact_check_weight' => 0.6,
        'wayback_verification_weight' => 0.2,
        'news_cross_reference_weight' => 0.2,

        'fact_check_ratings' => [
            'true' => 100,
            'mostly_true' => 80,
            'half_true' => 50,
            'mostly_false' => 20,
            'false' => 0,
            'pants_on_fire' => -20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Historical Accuracy Configuration
    |--------------------------------------------------------------------------
    */
    'historical_accuracy' => [
        'tracking_period' => 365, // days
        'minimum_articles' => 10,
        'accuracy_threshold' => 0.8,

        'performance_metrics' => [
            'fact_check_accuracy' => 0.5,
            'correction_frequency' => 0.3,
            'retraction_rate' => 0.2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for categorizing credibility scores
    |
    */
    'thresholds' => [
        'highly_credible' => 85,
        'credible' => 70,
        'moderately_credible' => 55,
        'low_credibility' => 40,
        'not_credible' => 25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Known Sources Configuration
    |--------------------------------------------------------------------------
    */
    'known_sources' => [
        'highly_trusted' => [
            'reuters.com', 'apnews.com', 'bbc.com', 'npr.org',
            'pbs.org', 'cspan.org', 'factcheck.org', 'snopes.com',
            'politifact.com', 'cdc.gov', 'who.int', 'nih.gov',
        ],

        'trusted_news' => [
            'nytimes.com', 'washingtonpost.com', 'wsj.com',
            'theguardian.com', 'economist.com', 'ft.com',
            'usatoday.com', 'latimes.com', 'chicagotribune.com',
        ],

        'academic_sources' => [
            // Will be populated with .edu domains dynamically
        ],

        'government_sources' => [
            // Will be populated with .gov domains dynamically
        ],

        'known_biased' => [
            // Sources with known political bias (configured by admin)
        ],

        'unreliable_sources' => [
            // Sources known for misinformation (configured by admin)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Machine Learning Configuration
    |--------------------------------------------------------------------------
    */
    'ml_features' => [
        'enabled' => env('CREDIBILITY_ML_ENABLED', false),
        'model_path' => storage_path('app/ml/credibility_model.pkl'),
        'feature_extraction' => [
            'text_features' => true,
            'domain_features' => true,
            'metadata_features' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    */
    'caching' => [
        'domain_scores_ttl' => 86400,    // 24 hours
        'content_scores_ttl' => 3600,    // 1 hour
        'bias_analysis_ttl' => 7200,     // 2 hours
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging and Monitoring
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'log_scoring_decisions' => true,
        'log_threshold_changes' => true,
        'performance_monitoring' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Integration
    |--------------------------------------------------------------------------
    */
    'api_integration' => [
        'newsguard_api' => [
            'enabled' => env('NEWSGUARD_ENABLED', false),
            'api_key' => env('NEWSGUARD_API_KEY'),
            'weight' => 0.3,
        ],

        'media_bias_fact_check' => [
            'enabled' => env('MBFC_ENABLED', false),
            'scraping_enabled' => true,
            'weight' => 0.2,
        ],

        'allsides_bias_rating' => [
            'enabled' => env('ALLSIDES_ENABLED', false),
            'scraping_enabled' => true,
            'weight' => 0.2,
        ],
    ],
];
