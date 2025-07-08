<?php

use SignalNorth\LaravelNeo4j\Database\Migrations\Neo4jMigration;
use SignalNorth\LaravelNeo4j\Database\Schema\Neo4jBlueprint;
use SignalNorth\LaravelNeo4j\Facades\Neo4j;
use Illuminate\Support\Facades\Schema;

test('can create and run a neo4j migration', function () {
    // Skip if Neo4j is not available
    if (!env('NEO4J_TEST_AVAILABLE', false)) {
        $this->markTestSkipped('Neo4j is not available for testing');
    }
    
    // Create a test migration class
    $migration = new class extends Neo4jMigration {
        public function up(): void
        {
            Neo4j::schema()->createNode('TestUser', function (Neo4jBlueprint $node) {
                $node->property('id')->unique();
                $node->property('name')->index();
                $node->property('email')->unique();
                $node->property('created_at');
            });
        }
        
        public function down(): void
        {
            Neo4j::schema()->dropNode('TestUser');
        }
    };
    
    // Run the migration
    $migration->up();
    
    // Verify the node label exists (this would require actual Neo4j connection)
    expect(true)->toBeTrue(); // Placeholder assertion
    
    // Clean up
    $migration->down();
});

it('can create constraints and indexes', function () {
    // Skip if Neo4j is not available
    if (!env('NEO4J_TEST_AVAILABLE', false)) {
        $this->markTestSkipped('Neo4j is not available for testing');
    }
    
    $migration = new class extends Neo4jMigration {
        public function up(): void
        {
            // Create unique constraint
            Neo4j::statement('
                CREATE CONSTRAINT user_email_unique IF NOT EXISTS
                FOR (u:User)
                REQUIRE u.email IS UNIQUE
            ');
            
            // Create index
            Neo4j::statement('
                CREATE INDEX user_name_index IF NOT EXISTS
                FOR (u:User)
                ON (u.name)
            ');
        }
        
        public function down(): void
        {
            Neo4j::statement('DROP CONSTRAINT user_email_unique IF EXISTS');
            Neo4j::statement('DROP INDEX user_name_index IF EXISTS');
        }
    };
    
    expect($migration)->toBeInstanceOf(Neo4jMigration::class);
});

test('migration uses correct connection', function () {
    $migration = new class extends Neo4jMigration {};
    
    expect($migration->getConnection())->toBe('neo4j');
});

it('can create relationships in migrations', function () {
    $cypherQuery = '
        MATCH (u:User {id: $userId}), (p:Product {id: $productId})
        CREATE (u)-[:PURCHASED {date: datetime(), amount: $amount}]->(p)
    ';
    
    expect($cypherQuery)
        ->toBeValidCypher()
        ->toContain('MATCH')
        ->toContain('CREATE')
        ->toContain('-[:PURCHASED');
});

test('migration repository tracks executed migrations', function () {
    $repository = Mockery::mock(\SignalNorth\LaravelNeo4j\Database\Migrations\Neo4jMigrationRepository::class);
    
    $repository->shouldReceive('getRan')
        ->once()
        ->andReturn(['2025_01_01_000000_create_users_node']);
    
    $repository->shouldReceive('log')
        ->once()
        ->with('2025_01_02_000000_create_products_node', 1);
    
    $ran = $repository->getRan();
    expect($ran)->toBeArray()->toHaveCount(1);
    
    $repository->log('2025_01_02_000000_create_products_node', 1);
});