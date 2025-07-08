<?php

require_once __DIR__ . '/vendor/autoload.php';

use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;

// Test Neo4j connection using the laudis/neo4j-php-client directly
try {
    echo "Testing Neo4j connection...\n";
    
    // Default configuration from config/neo4j.php
    $host = getenv('NEO4J_HOST') ?: 'localhost';
    $port = getenv('NEO4J_PORT') ?: '7687';
    $username = getenv('NEO4J_USERNAME') ?: 'neo4j';
    $password = getenv('NEO4J_PASSWORD') ?: '';
    $database = getenv('NEO4J_DATABASE') ?: 'neo4j';
    
    echo "Connecting to: bolt://{$host}:{$port}\n";
    echo "Username: {$username}\n";
    echo "Database: {$database}\n";
    
    $client = ClientBuilder::create()
        ->withDriver('bolt', "bolt://{$host}:{$port}", 
            $username ? 
                Laudis\Neo4j\Authentication\Authenticate::basic($username, $password) :
                null
        )
        ->withDefaultDriver('bolt')
        ->build();
    
    // Test basic connection
    $result = $client->run('RETURN "Hello Neo4j!" as greeting');
    $record = $result->first();
    
    echo "✓ Connection successful!\n";
    echo "Response: " . $record->get('greeting') . "\n";
    
    // Test database info
    $result = $client->run('CALL db.info()');
    $info = $result->first();
    
    echo "✓ Database info retrieved!\n";
    echo "Database ID: " . $info->get('id') . "\n";
    echo "Database Name: " . $info->get('name') . "\n";
    
    // Test basic query
    $result = $client->run('MATCH (n) RETURN count(n) as node_count');
    $count = $result->first();
    
    echo "✓ Query executed successfully!\n";
    echo "Total nodes in database: " . $count->get('node_count') . "\n";
    
} catch (Exception $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
    echo "Error details: " . $e->getTraceAsString() . "\n";
    exit(1);
}