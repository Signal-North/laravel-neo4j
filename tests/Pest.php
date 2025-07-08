<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(SignalNorth\LaravelNeo4j\Tests\TestCase::class);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// Custom expectations for Neo4j testing
expect()->extend('toBeValidCypher', function () {
    return $this->toBeString()
        ->toContain('MATCH')
        ->or
        ->toContain('CREATE')
        ->or
        ->toContain('MERGE');
});

expect()->extend('toBeNodeLabel', function () {
    return $this->toBeString()
        ->toMatch('/^[A-Z][a-zA-Z0-9]*$/');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a mock Neo4j client for testing
 */
function mockNeo4jClient()
{
    return Mockery::mock(\Laudis\Neo4j\Contracts\ClientInterface::class);
}

/**
 * Create a mock query builder
 */
function mockQueryBuilder()
{
    return Mockery::mock(\Illuminate\Database\Query\Builder::class);
}

/**
 * Assert that a Cypher query contains expected pattern
 */
function assertCypherContains(string $cypher, string $pattern): void
{
    expect($cypher)->toContain($pattern);
}

/**
 * Assert that a Cypher query is valid
 */
function assertValidCypher(string $cypher): void
{
    expect($cypher)->toBeValidCypher();
}