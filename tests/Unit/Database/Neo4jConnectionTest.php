<?php

use SignalNorth\LaravelNeo4j\Database\Neo4jConnection;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;

beforeEach(function () {
    $this->client = mockNeo4jClient();
    $this->connection = new Neo4jConnection($this->client, 'neo4j', '', []);
});

test('neo4j connection extends laravel connection', function () {
    expect($this->connection)->toBeInstanceOf(\Illuminate\Database\Connection::class);
});

test('driver title returns neo4j', function () {
    expect($this->connection->getDriverTitle())->toBe('Neo4j');
});

it('creates session when needed', function () {
    $session = Mockery::mock(SessionInterface::class);
    $this->client->shouldReceive('createSession')->once()->andReturn($session);
    
    $result = $this->connection->getNeo4jSession();
    
    expect($result)->toBe($session);
});

it('reuses existing session', function () {
    $session = Mockery::mock(SessionInterface::class);
    $this->client->shouldReceive('createSession')->once()->andReturn($session);
    
    // First call creates session
    $result1 = $this->connection->getNeo4jSession();
    // Second call should reuse
    $result2 = $this->connection->getNeo4jSession();
    
    expect($result1)->toBe($result2);
});

test('select method executes cypher query', function () {
    $session = Mockery::mock(SessionInterface::class);
    $result = Mockery::mock();
    
    $this->client->shouldReceive('createSession')->once()->andReturn($session);
    $session->shouldReceive('run')
        ->with('MATCH (n) RETURN n', [])
        ->once()
        ->andReturn($result);
    
    $result->shouldReceive('getIterator')->andReturn(new ArrayIterator([]));
    
    $results = $this->connection->select('MATCH (n) RETURN n');
    
    expect($results)->toBeArray();
});

it('handles transactions correctly', function () {
    $session = Mockery::mock(SessionInterface::class);
    $transaction = Mockery::mock(TransactionInterface::class);
    
    $this->client->shouldReceive('createSession')->once()->andReturn($session);
    $session->shouldReceive('beginTransaction')->once()->andReturn($transaction);
    $transaction->shouldReceive('commit')->once();
    
    $this->connection->beginTransaction();
    expect($this->connection->transactionLevel())->toBe(1);
    
    $this->connection->commit();
    expect($this->connection->transactionLevel())->toBe(0);
});

test('rollback works correctly', function () {
    $session = Mockery::mock(SessionInterface::class);
    $transaction = Mockery::mock(TransactionInterface::class);
    
    $this->client->shouldReceive('createSession')->once()->andReturn($session);
    $session->shouldReceive('beginTransaction')->once()->andReturn($transaction);
    $transaction->shouldReceive('rollback')->once();
    
    $this->connection->beginTransaction();
    $this->connection->rollBack();
    
    expect($this->connection->transactionLevel())->toBe(0);
});

it('returns null for PDO methods', function () {
    expect($this->connection->getPdo())->toBeNull();
    expect($this->connection->getReadPdo())->toBeNull();
});

test('statement method returns boolean', function () {
    $session = Mockery::mock(SessionInterface::class);
    $result = Mockery::mock();
    
    $this->client->shouldReceive('createSession')->once()->andReturn($session);
    $session->shouldReceive('run')->once()->andReturn($result);
    
    $success = $this->connection->statement('CREATE (n:Test)');
    
    expect($success)->toBeBool()->toBeTrue();
});

it('can get neo4j client instance', function () {
    expect($this->connection->getNeo4jClient())->toBe($this->client);
});