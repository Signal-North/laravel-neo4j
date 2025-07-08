<?php

use SignalNorth\LaravelNeo4j\Database\Query\Grammars\Neo4jGrammar;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Connection;

beforeEach(function () {
    $this->connection = Mockery::mock(Connection::class);
    $this->grammar = new Neo4jGrammar();
    $this->builder = mockQueryBuilder();
});

afterEach(function () {
    Mockery::close();
});

it('compiles select query with all columns', function () {
    $this->builder->from = 'users';
    $this->builder->columns = ['*'];
    
    $cypher = $this->grammar->compileSelect($this->builder);
    
    assertCypherContains($cypher, 'MATCH (user:`users`)');
    assertCypherContains($cypher, 'RETURN user');
    assertValidCypher($cypher);
});

it('compiles select query with specific columns', function () {
    $this->builder->from = 'users';
    $this->builder->columns = ['name', 'email'];
    
    $cypher = $this->grammar->compileSelect($this->builder);
    
    expect($cypher)
        ->toContain('MATCH (user:`users`)')
        ->toContain('RETURN user.`name`, user.`email`');
});

test('compile insert creates valid CREATE statement', function () {
    $this->builder->from = 'users';
    $values = ['name' => 'John', 'email' => 'john@example.com'];
    
    $cypher = $this->grammar->compileInsert($this->builder, $values);
    
    expect($cypher)
        ->toBeValidCypher()
        ->toContain('CREATE')
        ->toContain('`users`')
        ->toContain('RETURN *');
});

test('compile update creates valid MATCH...SET statement', function () {
    $this->builder->from = 'users';
    $values = ['name' => 'Updated Name'];
    
    $cypher = $this->grammar->compileUpdate($this->builder, $values);
    
    expect($cypher)
        ->toBeValidCypher()
        ->toContain('MATCH (user:`users`)')
        ->toContain('SET user.`name`')
        ->toContain('RETURN user');
});

test('compile delete creates valid MATCH...DELETE statement', function () {
    $this->builder->from = 'users';
    
    $cypher = $this->grammar->compileDelete($this->builder);
    
    expect($cypher)
        ->toBeValidCypher()
        ->toContain('MATCH (user:`users`)')
        ->toContain('DELETE user');
});

it('converts SQL LIMIT to Cypher LIMIT', function () {
    $this->builder->from = 'products';
    $this->builder->columns = ['*'];
    $this->builder->limit = 10;
    
    $cypher = $this->grammar->compileSelect($this->builder);
    
    expect($cypher)->toContain('LIMIT 10');
});

it('converts SQL OFFSET to Cypher SKIP', function () {
    $this->builder->from = 'products';
    $this->builder->columns = ['*'];
    $this->builder->offset = 20;
    
    $cypher = $this->grammar->compileSelect($this->builder);
    
    expect($cypher)->toContain('SKIP 20');
});

it('supports Neo4j-specific operators', function () {
    $operators = (new ReflectionClass($this->grammar))->getProperty('operators');
    $operators->setAccessible(true);
    $neo4jOperators = $operators->getValue($this->grammar);
    
    expect($neo4jOperators)
        ->toContain('CONTAINS')
        ->toContain('STARTS WITH')
        ->toContain('ENDS WITH')
        ->toContain('REGEX');
});

test('node variable is derived from table name', function () {
    $this->builder->from = 'products';
    $this->builder->columns = ['*'];
    
    $cypher = $this->grammar->compileSelect($this->builder);
    
    // 'products' should become 'product' (singular)
    expect($cypher)->toContain('(product:`products`)');
});

it('handles ORDER BY clauses correctly', function () {
    $this->builder->from = 'users';
    $this->builder->columns = ['*'];
    $this->builder->orders = [
        ['column' => 'name', 'direction' => 'asc'],
        ['column' => 'created_at', 'direction' => 'desc']
    ];
    
    $cypher = $this->grammar->compileSelect($this->builder);
    
    expect($cypher)->toContain('ORDER BY user.`name` ASC, user.`created_at` DESC');
});