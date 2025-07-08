<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Neo4j Connection
    |--------------------------------------------------------------------------
    |
    | This option controls the default Neo4j connection for your application.
    | You may define multiple connections below and switch between them.
    |
    */
    
    'default' => env('NEO4J_CONNECTION', 'neo4j'),

    /*
    |--------------------------------------------------------------------------
    | Neo4j Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection settings for Neo4j. The default
    | configuration uses the Bolt protocol which is recommended for production.
    | You may also use HTTP or HTTPS protocols if needed.
    |
    | Supported schemes: "bolt", "http", "https"
    |
    */

    'connections' => [
        'neo4j' => [
            'driver' => 'neo4j',
            'scheme' => env('NEO4J_SCHEME', 'bolt'),
            'host' => env('NEO4J_HOST', 'localhost'),
            'port' => env('NEO4J_PORT', 7687),
            'username' => env('NEO4J_USERNAME', 'neo4j'),
            'password' => env('NEO4J_PASSWORD', ''),
            'database' => env('NEO4J_DATABASE', 'neo4j'),
            
            // Connection options
            'timeout' => env('NEO4J_TIMEOUT', 30),
            'ssl' => env('NEO4J_SSL', false),
            'ssl_config' => [
                'verify_peer' => env('NEO4J_SSL_VERIFY_PEER', true),
                'verify_peer_name' => env('NEO4J_SSL_VERIFY_PEER_NAME', true),
            ],
            
            // Query logging
            'logging' => env('NEO4J_LOGGING', true),
            'log_channel' => env('NEO4J_LOG_CHANNEL', 'stack'),
            
            // Connection pooling
            'pool' => [
                'min_connections' => env('NEO4J_POOL_MIN', 1),
                'max_connections' => env('NEO4J_POOL_MAX', 50),
                'keep_alive' => env('NEO4J_POOL_KEEP_ALIVE', true),
            ],
            
            // Performance options
            'cache' => [
                'enabled' => env('NEO4J_CACHE_ENABLED', true),
                'ttl' => env('NEO4J_CACHE_TTL', 3600),
                'prefix' => env('NEO4J_CACHE_PREFIX', 'neo4j_cache'),
            ],
        ],
        
        // Example read replica configuration
        'neo4j_read' => [
            'driver' => 'neo4j',
            'scheme' => env('NEO4J_READ_SCHEME', 'bolt'),
            'host' => env('NEO4J_READ_HOST', 'localhost'),
            'port' => env('NEO4J_READ_PORT', 7687),
            'username' => env('NEO4J_READ_USERNAME', 'neo4j'),
            'password' => env('NEO4J_READ_PASSWORD', ''),
            'database' => env('NEO4J_READ_DATABASE', 'neo4j'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Configuration
    |--------------------------------------------------------------------------
    |
    | These options configure the Neo4j migration system. You may specify
    | the table used to track migrations and the default migration path.
    |
    */
    
    'migrations' => [
        'table' => env('NEO4J_MIGRATIONS_TABLE', 'neo4j_migrations'),
        'path' => database_path('neo4j-migrations'),
        'namespace' => 'Database\\Neo4jMigrations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Builder Settings
    |--------------------------------------------------------------------------
    |
    | Configure the behavior of the Neo4j query builder including default
    | node labels, relationship types, and query optimization settings.
    |
    */
    
    'query_builder' => [
        'default_limit' => env('NEO4J_DEFAULT_LIMIT', 1000),
        'enable_profiling' => env('NEO4J_PROFILING', false),
        'slow_query_threshold' => env('NEO4J_SLOW_QUERY_MS', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Management
    |--------------------------------------------------------------------------
    |
    | Settings for Neo4j schema management including constraint and index
    | creation options.
    |
    */
    
    'schema' => [
        'auto_index' => env('NEO4J_AUTO_INDEX', true),
        'constraint_mode' => env('NEO4J_CONSTRAINT_MODE', 'create_or_update'),
    ],
];