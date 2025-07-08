# Laravel Neo4j

[![Latest Version](https://img.shields.io/packagist/v/signalnorth/laravel-neo4j.svg?style=flat-square)](https://packagist.org/packages/signalnorth/laravel-neo4j)
[![Total Downloads](https://img.shields.io/packagist/dt/signalnorth/laravel-neo4j.svg?style=flat-square)](https://packagist.org/packages/signalnorth/laravel-neo4j)
[![License](https://img.shields.io/packagist/l/signalnorth/laravel-neo4j.svg?style=flat-square)](https://packagist.org/packages/signalnorth/laravel-neo4j)

A comprehensive Neo4j integration for Laravel, providing a complete database driver with migration support, query builder, and Eloquent-style models for graph databases.

## Features

- =� **Full Laravel Integration** - Works seamlessly with Laravel's database layer
- =� **Migration Support** - Version control your graph database schema
- =
 **Query Builder** - Fluent interface for building Cypher queries
- <� **Eloquent-style Models** - Familiar syntax for graph operations
- =' **Artisan Commands** - Manage your Neo4j database from the CLI
- <� **Schema Builder** - Create constraints and indexes programmatically
- = **Transaction Support** - Full ACID compliance with rollback capabilities
- =� **Performance Optimised** - Connection pooling and query caching

## Requirements

- PHP 8.1, 8.2, or 8.3
- Laravel 10.x or 11.x
- Neo4j 4.4+ or 5.0+
- ext-bcmath and ext-sockets PHP extensions

## Installation

Install the package via Composer:

```bash
composer require signalnorth/laravel-neo4j
```

Run the installation command:

```bash
php artisan neo4j:install
```

This will:
- Publish the configuration file
- Create the Neo4j migrations directory
- Add environment variables to `.env.example`

## Configuration

Add your Neo4j connection details to your `.env` file:

```env
NEO4J_SCHEME=bolt
NEO4J_HOST=localhost
NEO4J_PORT=7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=your-password
NEO4J_DATABASE=neo4j
```

The full configuration file is published to `config/neo4j.php` and includes options for:
- Multiple connections
- Connection pooling
- Query caching
- SSL/TLS settings
- Migration paths

## Usage

### Basic Queries

Using the facade:

```php
use SignalNorth\LaravelNeo4j\Facades\Neo4j;

// Create a node
Neo4j::statement('CREATE (u:User {name: $name, email: $email})', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Query nodes
$users = Neo4j::select('MATCH (u:User) RETURN u');

// Update nodes
Neo4j::update('MATCH (u:User {email: $email}) SET u.name = $name', [
    'email' => 'john@example.com',
    'name' => 'Jane Doe'
]);

// Delete nodes
Neo4j::delete('MATCH (u:User {email: $email}) DELETE u', [
    'email' => 'john@example.com'
]);
```

### Using Query Builder

```php
use Illuminate\Support\Facades\DB;

// Get all users
$users = DB::connection('neo4j')
    ->table('User')
    ->get();

// Find specific user
$user = DB::connection('neo4j')
    ->table('User')
    ->where('email', 'john@example.com')
    ->first();

// Create relationships
DB::connection('neo4j')->statement('
    MATCH (u:User {id: $userId}), (p:Product {id: $productId})
    CREATE (u)-[:PURCHASED {date: datetime()}]->(p)
', [
    'userId' => 1,
    'productId' => 100
]);
```

### Migrations

Create a new migration:

```bash
php artisan neo4j:make:migration create_user_nodes --create=User
```

Example migration:

```php
<?php

use SignalNorth\LaravelNeo4j\Database\Migrations\Neo4jMigration;
use SignalNorth\LaravelNeo4j\Database\Schema\Neo4jBlueprint;
use SignalNorth\LaravelNeo4j\Facades\Neo4j;

class CreateUserNodes extends Neo4jMigration
{
    public function up(): void
    {
        Neo4j::schema()->createNode('User', function (Neo4jBlueprint $node) {
            $node->property('id')->unique();
            $node->property('name')->index();
            $node->property('email')->unique();
            $node->property('created_at');
            $node->property('updated_at');
        });
    }

    public function down(): void
    {
        Neo4j::schema()->dropNode('User');
    }
}
```

Run migrations:

```bash
php artisan neo4j:migrate
```

### Transactions

```php
use SignalNorth\LaravelNeo4j\Facades\Neo4j;

Neo4j::transaction(function () {
    Neo4j::statement('CREATE (u:User {name: $name})', ['name' => 'John']);
    Neo4j::statement('CREATE (p:Product {name: $name})', ['name' => 'Widget']);
    // If any query fails, all are rolled back
});

// Manual transaction control
Neo4j::beginTransaction();
try {
    // Your queries here
    Neo4j::commit();
} catch (\Exception $e) {
    Neo4j::rollBack();
    throw $e;
}
```

### Schema Management

```php
use SignalNorth\LaravelNeo4j\Facades\Neo4j;

// Create constraint
Neo4j::schema()->createConstraint('User', 'email', 'unique');

// Create index
Neo4j::schema()->createIndex('Product', ['name', 'category']);

// Drop constraint
Neo4j::schema()->dropConstraint('User_email_unique');

// Drop index
Neo4j::schema()->dropIndex('Product_name_category_index');
```

## Artisan Commands

```bash
# Check connection status
php artisan neo4j:status

# Create migrations
php artisan neo4j:make:migration create_product_nodes --create=Product
php artisan neo4j:make:migration add_user_constraint --constraint=User
php artisan neo4j:make:migration add_product_index --index=Product

# Run migrations
php artisan neo4j:migrate

# Install package
php artisan neo4j:install --with-example
```

## Advanced Features

### Multiple Connections

Configure multiple Neo4j connections in `config/neo4j.php`:

```php
'connections' => [
    'default' => [
        // Main connection
    ],
    'analytics' => [
        // Analytics cluster
    ],
]
```

Use specific connections:

```php
$results = DB::connection('neo4j_analytics')->select('...');
```

### Query Logging

Enable query logging in your configuration:

```php
'logging' => true,
'log_channel' => 'neo4j',
```

### Performance Monitoring

Monitor slow queries:

```php
'query_builder' => [
    'slow_query_threshold' => 1000, // milliseconds
    'enable_profiling' => true,
]
```

## Testing

The package uses Pest PHP for testing, providing a clean and expressive testing experience. To run tests:

```bash
composer test
```

For test coverage:

```bash
composer test-coverage
```

### Writing Tests

Create tests using Pest's expressive syntax:

```php
// Unit test example
test('neo4j connection works', function () {
    $connection = DB::connection('neo4j');
    expect($connection)->toBeInstanceOf(Neo4jConnection::class);
});

// Feature test example with database
it('can create nodes', function () {
    Neo4j::statement('CREATE (u:User {name: $name})', ['name' => 'Test User']);
    
    $result = Neo4j::select('MATCH (u:User {name: $name}) RETURN u', ['name' => 'Test User']);
    
    expect($result)->toHaveCount(1);
});
```

### Custom Expectations

The package includes custom expectations for Neo4j:

```php
// Check if a string is valid Cypher
expect($query)->toBeValidCypher();

// Check if a string is a valid node label
expect('User')->toBeNodeLabel();
```

### Test Helpers

```php
// Mock Neo4j client
$client = mockNeo4jClient();

// Assert Cypher queries
assertCypherContains($query, 'MATCH');
assertValidCypher($query);
```

## Troubleshooting

### Connection Issues

1. Verify Neo4j is running:
   ```bash
   php artisan neo4j:status --detailed
   ```

2. Check credentials in `.env`

3. Ensure required PHP extensions are installed:
   ```bash
   php -m | grep -E '(bcmath|sockets)'
   ```

### Migration Issues

1. Check migration path exists:
   ```bash
   ls database/neo4j-migrations/
   ```

2. Verify connection in migration:
   ```php
   protected $connection = 'neo4j';
   ```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@signalnorth.com instead of using the issue tracker.

## Credits

- [SignalNorth Team](https://github.com/signalnorth)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.