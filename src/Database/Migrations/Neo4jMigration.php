<?php

namespace SignalNorth\LaravelNeo4j\Database\Migrations;

use Illuminate\Database\Migrations\Migration;

/**
 * Neo4j Migration Base Class
 *
 * Base class for Neo4j migrations providing common functionality
 * and ensuring migrations run on the correct connection.
 *
 * @pattern Template Pattern - Provides template for Neo4j migrations
 * @package SignalNorth\LaravelNeo4j\Database\Migrations
 * @since 1.0.0
 */
abstract class Neo4jMigration extends Migration
{
    /**
     * The database connection to use
     *
     * @var string
     */
    protected $connection = 'neo4j';

    /**
     * Get the migration connection name
     *
     * @return string|null
     */
    public function getConnection(): ?string
    {
        return $this->connection ?: config('neo4j.default', 'neo4j');
    }
}