<?php

namespace SignalNorth\LaravelNeo4j\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SignalNorth\LaravelNeo4j\Neo4jServiceProvider;

/**
 * Base Test Case
 *
 * Provides common test functionality and package setup for all tests.
 *
 * @pattern Template Pattern - Base test class for package tests
 * @package SignalNorth\LaravelNeo4j\Tests
 * @since 1.0.0
 */
abstract class TestCase extends Orchestra
{
    /**
     * Set up the test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Additional setup if needed
    }

    /**
     * Get package providers
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            Neo4jServiceProvider::class,
        ];
    }

    /**
     * Define environment setup
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Set up test database configuration
        $app['config']->set('database.connections.neo4j', [
            'driver' => 'neo4j',
            'scheme' => 'bolt',
            'host' => env('NEO4J_TEST_HOST', 'localhost'),
            'port' => env('NEO4J_TEST_PORT', 7687),
            'username' => env('NEO4J_TEST_USERNAME', 'neo4j'),
            'password' => env('NEO4J_TEST_PASSWORD', 'test'),
            'database' => env('NEO4J_TEST_DATABASE', 'neo4j'),
        ]);
        
        $app['config']->set('database.default', 'neo4j');
    }
}