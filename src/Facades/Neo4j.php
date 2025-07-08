<?php

namespace SignalNorth\LaravelNeo4j\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Neo4j Facade
 *
 * Provides a convenient static interface to Neo4j database operations.
 * This facade proxies method calls to the underlying Neo4j connection.
 *
 * @pattern Facade Pattern - Simplified interface to Neo4j connection
 * @package SignalNorth\LaravelNeo4j\Facades
 * @since 1.0.0
 * 
 * @method static array select(string $query, array $bindings = [])
 * @method static bool insert(string $query, array $bindings = [])
 * @method static int update(string $query, array $bindings = [])
 * @method static int delete(string $query, array $bindings = [])
 * @method static bool statement(string $query, array $bindings = [])
 * @method static mixed transaction(\Closure $callback, int $attempts = 1)
 * @method static void beginTransaction()
 * @method static void commit()
 * @method static void rollBack()
 * @method static \SignalNorth\LaravelNeo4j\Database\Neo4jConnection connection(string $name = null)
 * @method static \SignalNorth\LaravelNeo4j\Database\Schema\Neo4jSchemaBuilder schema()
 * @method static \Laudis\Neo4j\Contracts\ClientInterface getClient()
 * 
 * @see \SignalNorth\LaravelNeo4j\Database\Neo4jConnection
 */
class Neo4j extends Facade
{
    /**
     * Get the registered name of the component
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'neo4j.connection';
    }
}