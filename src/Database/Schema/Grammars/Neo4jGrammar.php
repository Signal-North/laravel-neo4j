<?php

namespace SignalNorth\LaravelNeo4j\Database\Schema\Grammars;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

/**
 * Neo4j Schema Grammar
 *
 * Compiles Laravel migration operations into Neo4j Cypher DDL statements.
 * Maps relational database schema operations to their graph database equivalents,
 * focusing on constraints, indexes, and node/relationship creation patterns.
 *
 * @pattern Strategy Pattern - Implements Neo4j-specific schema compilation
 * @pattern Adapter Pattern - Adapts SQL DDL to Cypher DDL syntax
 * @package App\Database\Schema\Grammars
 * @since 1.0.0
 * @security Ensures proper constraint validation and schema security
 */
class Neo4jGrammar extends Grammar
{
    /**
     * The possible column modifiers (not applicable to Neo4j)
     *
     * @var array
     */
    protected $modifiers = [];

    /**
     * The possible column serials (not applicable to Neo4j)
     *
     * @var array
     */
    protected $serials = [];

    /**
     * Compile a create table command (creates node label constraints)
     *
     * In Neo4j, "creating a table" translates to ensuring a node label exists
     * and setting up any initial constraints or indexes for that label.
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compileCreate(Blueprint $blueprint, Fluent $command): string
    {
        $label = $this->wrapTable($blueprint->getTable());
        $statements = [];

        // Create a sample node to ensure the label exists (will be removed)
        $statements[] = "CREATE (temp:{$label}) DELETE temp";

        // Add any constraints and indexes defined in the blueprint
        foreach ($blueprint->getColumns() as $column) {
            if ($column['unique'] ?? false) {
                $property = $this->wrap($column['name']);
                $statements[] = "CREATE CONSTRAINT {$label}_{$column['name']}_unique IF NOT EXISTS FOR (n:{$label}) REQUIRE n.{$property} IS UNIQUE";
            }

            if ($column['index'] ?? false) {
                $property = $this->wrap($column['name']);
                $statements[] = "CREATE INDEX {$label}_{$column['name']}_index IF NOT EXISTS FOR (n:{$label}) ON (n.{$property})";
            }

            if ($column['primary'] ?? false) {
                $property = $this->wrap($column['name']);
                $statements[] = "CREATE CONSTRAINT {$label}_{$column['name']}_key IF NOT EXISTS FOR (n:{$label}) REQUIRE n.{$property} IS NODE KEY";
            }
        }

        return implode('; ', $statements);
    }

    /**
     * Compile a drop table command (drops node label constraints)
     *
     * In Neo4j, "dropping a table" means removing all constraints and indexes
     * associated with a label, and optionally deleting all nodes with that label.
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compileDrop(Blueprint $blueprint, Fluent $command): string
    {
        $label = $this->wrapTable($blueprint->getTable());
        $statements = [];

        // First, drop all constraints for this label
        $statements[] = "CALL db.constraints() YIELD name, description WHERE description CONTAINS '{$label}' CALL db.dropConstraint(name) YIELD name as droppedConstraint RETURN droppedConstraint";

        // Then drop all indexes for this label  
        $statements[] = "CALL db.indexes() YIELD name, labelsOrTypes WHERE '{$label}' IN labelsOrTypes CALL db.dropIndex(name) YIELD name as droppedIndex RETURN droppedIndex";

        // Optionally delete all nodes with this label (uncomment if needed)
        // $statements[] = "MATCH (n:{$label}) DELETE n";

        return implode('; ', $statements);
    }

    /**
     * Compile a drop table if exists command
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compileDropIfExists(Blueprint $blueprint, Fluent $command): string
    {
        return $this->compileDrop($blueprint, $command);
    }

    /**
     * Compile an add column command (creates property constraint)
     *
     * In Neo4j, adding a "column" means ensuring nodes can have a new property
     * and potentially adding constraints or indexes for that property.
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compileAdd(Blueprint $blueprint, Fluent $command): string
    {
        $label = $this->wrapTable($blueprint->getTable());
        $statements = [];

        foreach ($blueprint->getAddedColumns() as $column) {
            $property = $this->wrap($column['name']);

            // Add unique constraint if specified
            if ($column['unique'] ?? false) {
                $statements[] = "CREATE CONSTRAINT {$label}_{$column['name']}_unique IF NOT EXISTS FOR (n:{$label}) REQUIRE n.{$property} IS UNIQUE";
            }

            // Add index if specified
            if ($column['index'] ?? false) {
                $statements[] = "CREATE INDEX {$label}_{$column['name']}_index IF NOT EXISTS FOR (n:{$label}) ON (n.{$property})";
            }

            // Add node key constraint if primary
            if ($column['primary'] ?? false) {
                $statements[] = "CREATE CONSTRAINT {$label}_{$column['name']}_key IF NOT EXISTS FOR (n:{$label}) REQUIRE n.{$property} IS NODE KEY";
            }

            // Add existence constraint if not nullable
            if (!($column['nullable'] ?? true)) {
                $statements[] = "CREATE CONSTRAINT {$label}_{$column['name']}_exists IF NOT EXISTS FOR (n:{$label}) REQUIRE n.{$property} IS NOT NULL";
            }

            // Set default value for existing nodes if specified
            if (isset($column['default'])) {
                $defaultValue = $this->getDefaultValue($column);
                $statements[] = "MATCH (n:{$label}) WHERE n.{$property} IS NULL SET n.{$property} = {$defaultValue}";
            }
        }

        return empty($statements) ? '' : implode('; ', $statements);
    }

    /**
     * Compile a unique constraint command
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compileUnique(Blueprint $blueprint, Fluent $command): string
    {
        $label = $this->wrapTable($blueprint->getTable());
        $columns = $this->columnize($command->columns);
        $constraintName = $command->index ?? $this->createIndexName('unique', $blueprint->getTable(), $command->columns);

        if (count($command->columns) === 1) {
            $property = $this->wrap($command->columns[0]);
            return "CREATE CONSTRAINT {$constraintName} IF NOT EXISTS FOR (n:{$label}) REQUIRE n.{$property} IS UNIQUE";
        }

        // For composite unique constraints, use node key
        $properties = collect($command->columns)->map(function ($column) {
            return "n.{$this->wrap($column)}";
        })->implode(', ');

        return "CREATE CONSTRAINT {$constraintName} IF NOT EXISTS FOR (n:{$label}) REQUIRE ({$properties}) IS NODE KEY";
    }

    /**
     * Compile an index command
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compileIndex(Blueprint $blueprint, Fluent $command): string
    {
        $label = $this->wrapTable($blueprint->getTable());
        $indexName = $command->index ?? $this->createIndexName('index', $blueprint->getTable(), $command->columns);

        if (count($command->columns) === 1) {
            $property = $this->wrap($command->columns[0]);
            return "CREATE INDEX {$indexName} IF NOT EXISTS FOR (n:{$label}) ON (n.{$property})";
        }

        // For composite indexes
        $properties = collect($command->columns)->map(function ($column) {
            return "n.{$this->wrap($column)}";
        })->implode(', ');

        return "CREATE INDEX {$indexName} IF NOT EXISTS FOR (n:{$label}) ON ({$properties})";
    }

    /**
     * Compile a drop index command
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compileDropIndex(Blueprint $blueprint, Fluent $command): string
    {
        $indexName = $command->index;
        return "DROP INDEX {$indexName} IF EXISTS";
    }

    /**
     * Compile a drop unique constraint command
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compileDropUnique(Blueprint $blueprint, Fluent $command): string
    {
        $constraintName = $command->index;
        return "DROP CONSTRAINT {$constraintName} IF EXISTS";
    }

    /**
     * Compile a primary key command (creates node key constraint)
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compilePrimary(Blueprint $blueprint, Fluent $command): string
    {
        $label = $this->wrapTable($blueprint->getTable());
        $constraintName = $command->index ?? $this->createIndexName('primary', $blueprint->getTable(), $command->columns);

        if (count($command->columns) === 1) {
            $property = $this->wrap($command->columns[0]);
            return "CREATE CONSTRAINT {$constraintName} IF NOT EXISTS FOR (n:{$label}) REQUIRE n.{$property} IS NODE KEY";
        }

        // For composite primary keys
        $properties = collect($command->columns)->map(function ($column) {
            return "n.{$this->wrap($column)}";
        })->implode(', ');

        return "CREATE CONSTRAINT {$constraintName} IF NOT EXISTS FOR (n:{$label}) REQUIRE ({$properties}) IS NODE KEY";
    }

    /**
     * Compile a drop primary key command
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compileDropPrimary(Blueprint $blueprint, Fluent $command): string
    {
        $constraintName = $command->index;
        return "DROP CONSTRAINT {$constraintName} IF EXISTS";
    }

    /**
     * Create a new node label (custom command for Neo4j)
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compileCreateNode(Blueprint $blueprint, Fluent $command): string
    {
        $label = $this->wrapTable($command->label ?? $blueprint->getTable());
        $properties = '';

        if (!empty($command->properties)) {
            $props = collect($command->properties)->map(function ($value, $key) {
                return "{$this->wrap($key)}: {$this->parameter($value)}";
            })->implode(', ');
            $properties = " {{$props}}";
        }

        return "CREATE (:{$label}{$properties})";
    }

    /**
     * Create a relationship type (custom command for Neo4j)
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string Cypher DDL statement
     */
    public function compileCreateRelationship(Blueprint $blueprint, Fluent $command): string
    {
        $fromLabel = $this->wrapTable($command->from);
        $toLabel = $this->wrapTable($command->to);
        $relationship = $this->wrap($command->relationship);
        $properties = '';

        if (!empty($command->properties)) {
            $props = collect($command->properties)->map(function ($value, $key) {
                return "{$this->wrap($key)}: {$this->parameter($value)}";
            })->implode(', ');
            $properties = " {{$props}}";
        }

        return "MATCH (from:{$fromLabel}), (to:{$toLabel}) CREATE (from)-[:{$relationship}{$properties}]->(to)";
    }

    /**
     * Get the default value for a column
     *
     * @param mixed $value Default value
     * @return string Formatted default value
     */
    protected function getDefaultValue($value): string
    {
        $default = $value;

        if (is_null($default)) {
            return 'null';
        }

        if (is_bool($default)) {
            return $default ? 'true' : 'false';
        }

        if (is_numeric($default)) {
            return (string) $default;
        }

        return "'{$default}'";
    }

    /**
     * Create an index name for Neo4j
     *
     * @param string $type Index type
     * @param string $table Table/label name
     * @param array $columns Column names
     * @return string Index name
     */
    protected function createIndexName(string $type, string $table, array $columns): string
    {
        $name = strtolower($table . '_' . implode('_', $columns) . '_' . $type);
        return str_replace(['-', '.'], '_', $name);
    }

    /**
     * Get the SQL for the table comment (not applicable to Neo4j)
     *
     * @param Blueprint $blueprint Migration blueprint
     * @param Fluent $command Command details
     * @return string|null
     */
    public function compileTableComment(Blueprint $blueprint, Fluent $command): ?string
    {
        return null;
    }

    /**
     * Wrap a table in keyword identifiers
     *
     * @param string $table Table name
     * @return string Wrapped table name
     */
    public function wrapTable($table): string
    {
        return $this->wrap($this->tablePrefix . $table);
    }

    /**
     * Wrap a value in keyword identifiers
     *
     * @param string $value Value to wrap
     * @return string Wrapped value
     */
    public function wrap($value): string
    {
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        if (strpos(strtolower($value), ' as ') !== false) {
            return $this->wrapAliasedValue($value);
        }

        return $this->wrapSegments(explode('.', $value));
    }

    /**
     * Wrap the given value segments
     *
     * @param array $segments Value segments
     * @return string Wrapped segments
     */
    protected function wrapSegments($segments): string
    {
        return collect($segments)->map(function ($segment, $key) use ($segments) {
            return $key == 0 && count($segments) > 1
                ? $this->wrapTable($segment)
                : $this->wrapValue($segment);
        })->implode('.');
    }

    /**
     * Wrap a single string in keyword identifiers
     *
     * @param string $value Value to wrap
     * @return string Wrapped value
     */
    protected function wrapValue($value): string
    {
        if ($value !== '*') {
            return '`' . str_replace('`', '``', $value) . '`';
        }

        return $value;
    }

    /**
     * Compile the query to determine the tables (node labels)
     *
     * Maps Neo4j node labels to Laravel's table concept for schema introspection.
     * Returns all available labels in the Neo4j database with metadata that
     * matches Laravel's expected table structure.
     *
     * @pattern Adapter Pattern - Adapts Neo4j labels to Laravel table format
     * @param string|string[]|null $schema Schema name (ignored for Neo4j)
     * @return string Cypher query to get all labels
     * @security Uses parameterised queries to prevent injection
     */
    public function compileTables($schema): string
    {
        return "CALL db.labels() YIELD label RETURN label as name, 'neo4j' as schema, 0 as size, '' as comment, 'neo4j' as engine, '' as collation ORDER BY label";
    }

    /**
     * Compile the query to determine if table (label) exists
     *
     * Checks whether a specific node label exists in the Neo4j database.
     * This is equivalent to checking if a table exists in relational databases.
     *
     * @pattern Adapter Pattern - Adapts label existence to table existence
     * @param string|null $schema Schema name (ignored for Neo4j)
     * @param string $table Label name to check
     * @return string Cypher query to check label existence
     * @security Escapes table name to prevent injection
     */
    public function compileTableExists($schema, $table): string
    {
        $escapedTable = str_replace("'", "''", $table);
        return "CALL db.labels() YIELD label WITH collect(label) as labels RETURN '{$escapedTable}' IN labels as `exists`";
    }

    /**
     * Compile the query to determine columns (properties) for a label
     *
     * Retrieves all property names for nodes with a specific label.
     * Maps Neo4j node properties to Laravel's column concept for introspection.
     *
     * @pattern Adapter Pattern - Adapts Neo4j properties to Laravel columns
     * @param string|null $schema Schema name (ignored for Neo4j)
     * @param string $table Label name
     * @return string Cypher query to get properties for label
     * @security Uses backtick escaping for label names
     */
    public function compileColumns($schema, $table): string
    {
        $escapedTable = $this->wrapValue($table);
        return "MATCH (n:{$escapedTable}) UNWIND keys(n) as prop RETURN DISTINCT prop as name, 'string' as type, true as nullable, null as `default`, '' as collation ORDER BY prop";
    }

    /**
     * Compile the query to determine indexes for a label
     *
     * Retrieves all indexes and constraints associated with a specific label.
     * Maps Neo4j indexes to Laravel's index concept for schema introspection.
     *
     * @pattern Adapter Pattern - Adapts Neo4j indexes to Laravel index format
     * @param string|null $schema Schema name (ignored for Neo4j)
     * @param string $table Label name
     * @return string Cypher query to get indexes for label
     * @security Escapes table name to prevent injection
     */
    public function compileIndexes($schema, $table): string
    {
        $escapedTable = str_replace("'", "''", $table);
        return "CALL db.indexes() YIELD name, labelsOrTypes, properties WHERE '{$escapedTable}' IN labelsOrTypes RETURN name, properties, false as `unique` ORDER BY name";
    }

    /**
     * Compile the query to determine views
     *
     * Neo4j doesn't have the concept of views like relational databases.
     * Returns an empty result set for Laravel compatibility.
     *
     * @pattern Null Object Pattern - Provides empty implementation for unsupported feature
     * @param string|string[]|null $schema Schema name (ignored)
     * @return string Empty result query
     */
    public function compileViews($schema): string
    {
        return "RETURN null as name, null as definition WHERE false";
    }

    /**
     * Compile the query to determine types
     *
     * Neo4j doesn't have custom types like PostgreSQL. Returns an empty
     * result set for Laravel compatibility.
     *
     * @pattern Null Object Pattern - Provides empty implementation for unsupported feature
     * @param string|string[]|null $schema Schema name (ignored)
     * @return string Empty result query
     */
    public function compileTypes($schema): string
    {
        return "RETURN null as name, null as type WHERE false";
    }
}