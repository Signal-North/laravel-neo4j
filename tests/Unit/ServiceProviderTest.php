<?php

use SignalNorth\LaravelNeo4j\Neo4jServiceProvider;
use SignalNorth\LaravelNeo4j\Facades\Neo4j;

test('service provider is registered', function () {
    expect($this->app->providerIsLoaded(Neo4jServiceProvider::class))->toBeTrue();
});

test('neo4j facade is registered', function () {
    expect(new Neo4j())->toBeInstanceOf(\Illuminate\Support\Facades\Facade::class);
});

test('config is loaded', function () {
    expect($this->app['config']['database.connections.neo4j'])
        ->toBeArray()
        ->and($this->app['config']['database.connections.neo4j']['driver'])
        ->toBe('neo4j');
});

test('commands are registered', function () {
    $commands = [
        'neo4j:install',
        'neo4j:status',
        'neo4j:make:migration',
        'neo4j:migrate',
    ];

    $registeredCommands = $this->app[\Illuminate\Contracts\Console\Kernel::class]->all();
    
    foreach ($commands as $command) {
        expect($registeredCommands)->toHaveKey($command);
    }
});

test('neo4j connector is registered in container', function () {
    expect($this->app->bound('db.connector.neo4j'))->toBeTrue();
});

test('can resolve neo4j connection', function () {
    // Mock the connector to avoid actual connection attempts
    $this->app->bind('db.connector.neo4j', function () {
        return new class {
            public function connect($config) {
                return mockNeo4jClient();
            }
        };
    });
    
    expect($this->app->bound('neo4j.connection'))->toBeTrue();
});