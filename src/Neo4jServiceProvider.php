<?php

namespace SignalNorth\LaravelNeo4j;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use SignalNorth\LaravelNeo4j\Database\Connectors\Neo4jConnector;
use SignalNorth\LaravelNeo4j\Database\Neo4jConnection;
use SignalNorth\LaravelNeo4j\Console\Commands\Neo4jInstallCommand;
use SignalNorth\LaravelNeo4j\Console\Commands\Neo4jMakeMigrationCommand;
use SignalNorth\LaravelNeo4j\Console\Commands\Neo4jMigrateCommand;
use SignalNorth\LaravelNeo4j\Console\Commands\Neo4jStatusCommand;

/**
 * Neo4j Laravel Service Provider
 *
 * Registers Neo4j database driver and related services with Laravel.
 * Provides auto-discovery support and configuration publishing for
 * seamless integration with Laravel applications.
 *
 * @pattern Service Provider Pattern - Registers services with Laravel container
 * @pattern Factory Pattern - Creates database connections
 * @package SignalNorth\LaravelNeo4j
 * @since 1.0.0
 */
class Neo4jServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->extendDatabaseManager();
    }

    /**
     * Register the application services
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/neo4j.php', 'database.connections.neo4j'
        );

        $this->registerServices();
    }

    /**
     * Register the package's publishable resources
     *
     * @return void
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish configuration
            $this->publishes([
                __DIR__.'/../config/neo4j.php' => config_path('neo4j.php'),
            ], 'neo4j-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'neo4j-migrations');

            // Publish documentation
            $this->publishes([
                __DIR__.'/../docs' => base_path('docs/neo4j'),
            ], 'neo4j-docs');
        }
    }

    /**
     * Register the package's Artisan commands
     *
     * @return void
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Neo4jInstallCommand::class,
                Neo4jMakeMigrationCommand::class,
                Neo4jMigrateCommand::class,
                Neo4jStatusCommand::class,
            ]);
        }
    }

    /**
     * Register Neo4j services
     *
     * @return void
     */
    protected function registerServices(): void
    {
        // Register Neo4j connector
        $this->app->singleton('db.connector.neo4j', function () {
            return new Neo4jConnector();
        });

        // Register Neo4j connection resolver
        $this->app->bind('neo4j.connection', function ($app) {
            return $app['db']->connection('neo4j');
        });
    }

    /**
     * Extend the database manager with Neo4j driver
     *
     * @return void
     */
    protected function extendDatabaseManager(): void
    {
        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('neo4j', function ($config, $name) {
                $config['name'] = $name;
                
                // Get the connector
                $connector = $this->app['db.connector.neo4j'];
                
                // Create the Neo4j client
                $client = $connector->connect($config);
                
                // Create and return the connection
                return new Neo4jConnection(
                    $client,
                    $config['database'] ?? '',
                    $config['prefix'] ?? '',
                    $config
                );
            });
        });
    }

    /**
     * Get the services provided by the provider
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'db.connector.neo4j',
            'neo4j.connection',
        ];
    }
}