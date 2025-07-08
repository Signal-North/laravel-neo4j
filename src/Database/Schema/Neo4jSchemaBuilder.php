<?php

namespace SignalNorth\LaravelNeo4j\Database\Schema;

use SignalNorth\LaravelNeo4j\Database\Neo4jConnection;
use SignalNorth\LaravelNeo4j\Database\Schema\Grammars\Neo4jGrammar;
use Illuminate\Database\Schema\Builder;
use Closure;

/**
 * Neo4j Schema Builder
 *
 * Provides schema building operations for Neo4j graph database.
 * Extends Laravel's schema builder to handle graph-specific operations
 * like node labels, relationships, and graph constraints.
 *
 * @pattern Builder Pattern - Fluent interface for schema operations
 * @pattern Template Pattern - Overrides specific methods for Neo4j
 * @package App\Database\Schema
 * @since 1.0.0
 * @security Ensures safe schema operations and validates graph structure
 */
class Neo4jSchemaBuilder extends Builder
{
    /**
     * The Neo4j connection instance
     *
     * @var Neo4jConnection
     */
    protected $connection;

    /**
     * The schema grammar instance
     *
     * @var Neo4jGrammar
     */
    protected $grammar;

    /**
     * Create a new Neo4j schema builder instance
     *
     * @param Neo4jConnection $connection Neo4j connection instance
     */
    public function __construct(Neo4jConnection $connection)
    {
        $this->connection = $connection;
        $this->grammar = $connection->getSchemaGrammar();
    }

    /**
     * Create a new node label with the given closure
     *
     * Creates a new node label in the Neo4j database with properties,
     * constraints, and indexes as defined in the closure.
     *
     * @param string $label Node label name
     * @param Closure $callback Closure to define the node structure
     * @return void
     */
    public function createNode(string $label, Closure $callback): void
    {
        $this->build($this->createNodeBlueprint($label, $callback));
    }

    /**
     * Modify an existing node label
     *
     * Allows modification of existing node labels by adding new constraints,
     * indexes, or properties without affecting existing data.
     *
     * @param string $label Node label name
     * @param Closure $callback Closure to define modifications
     * @return void
     */
    public function modifyNode(string $label, Closure $callback): void
    {
        $blueprint = $this->createNodeBlueprint($label);
        $callback($blueprint);
        $this->build($blueprint);
    }

    /**
     * Drop a node label if it exists
     *
     * Removes a node label from the database along with all associated
     * constraints and indexes. Optionally deletes all nodes with this label.
     *
     * @param string $label Node label to drop
     * @param bool $deleteNodes Whether to delete all nodes with this label
     * @return void
     */
    public function dropNodeIfExists(string $label, bool $deleteNodes = false): void
    {
        $blueprint = $this->createNodeBlueprint($label);
        $blueprint->dropNodeLabel($label, $deleteNodes);
        $this->build($blueprint);
    }

    /**
     * Create a relationship type between node labels
     *
     * Defines a new relationship type that can connect nodes of specified labels.
     * Relationships can have properties and constraints.
     *
     * @param string $from Source node label
     * @param string $to Target node label
     * @param string $relationshipType Relationship type name
     * @param Closure|null $callback Optional closure for relationship properties
     * @return void
     */
    public function createRelationship(string $from, string $to, string $relationshipType, Closure $callback = null): void
    {
        $blueprint = $this->createNodeBlueprint($from);
        $command = $blueprint->createRelationship($from, $to, $relationshipType);
        
        if ($callback) {
            $callback($command);
        }
        
        $this->build($blueprint);
    }

    /**
     * Drop a relationship type
     *
     * Removes a relationship type from the database schema.
     * Optionally deletes all existing relationships of that type.
     *
     * @param string $relationshipType Relationship type to drop
     * @param bool $deleteRelationships Whether to delete all relationships
     * @return void
     */
    public function dropRelationshipType(string $relationshipType, bool $deleteRelationships = false): void
    {
        $blueprint = $this->createNodeBlueprint('_schema');
        $blueprint->dropRelationshipType($relationshipType, $deleteRelationships);
        $this->build($blueprint);
    }

    /**
     * Check if a node label exists
     *
     * Determines whether a node label exists in the database by checking
     * for nodes with that label.
     *
     * @param string $label Node label to check
     * @return bool True if label exists, false otherwise
     */
    public function hasNodeLabel(string $label): bool
    {
        $query = "CALL db.labels() YIELD label WHERE label = ? RETURN count(label) > 0 as exists";
        $result = $this->connection->select($query, [$label]);
        
        return !empty($result) && ($result[0]['exists'] ?? false);
    }

    /**
     * Check if a relationship type exists
     *
     * Determines whether a relationship type exists in the database.
     *
     * @param string $relationshipType Relationship type to check
     * @return bool True if relationship type exists, false otherwise
     */
    public function hasRelationshipType(string $relationshipType): bool
    {
        $query = "CALL db.relationshipTypes() YIELD relationshipType WHERE relationshipType = ? RETURN count(relationshipType) > 0 as exists";
        $result = $this->connection->select($query, [$relationshipType]);
        
        return !empty($result) && ($result[0]['exists'] ?? false);
    }

    /**
     * Get all node labels in the database
     *
     * Returns a list of all node labels currently present in the database.
     *
     * @return array List of node labels
     */
    public function getNodeLabels(): array
    {
        $query = "CALL db.labels() YIELD label RETURN label ORDER BY label";
        $result = $this->connection->select($query);
        
        return collect($result)->pluck('label')->toArray();
    }

    /**
     * Get all relationship types in the database
     *
     * Returns a list of all relationship types currently present in the database.
     *
     * @return array List of relationship types
     */
    public function getRelationshipTypes(): array
    {
        $query = "CALL db.relationshipTypes() YIELD relationshipType RETURN relationshipType ORDER BY relationshipType";
        $result = $this->connection->select($query);
        
        return collect($result)->pluck('relationshipType')->toArray();
    }

    /**
     * Get all constraints in the database
     *
     * Returns detailed information about all constraints in the database.
     *
     * @return array List of constraints with details
     */
    public function getConstraints(): array
    {
        $query = "CALL db.constraints() YIELD id, name, type, entityType, labelsOrTypes, properties, ownedIndexId RETURN *";
        return $this->connection->select($query);
    }

    /**
     * Get all indexes in the database
     *
     * Returns detailed information about all indexes in the database.
     *
     * @return array List of indexes with details
     */
    public function getIndexes(): array
    {
        $query = "CALL db.indexes() YIELD id, name, state, populationPercent, type, entityType, labelsOrTypes, properties RETURN *";
        return $this->connection->select($query);
    }

    /**
     * Get schema information for a specific node label
     *
     * Returns detailed schema information including properties, constraints,
     * and indexes for the specified node label.
     *
     * @param string $label Node label to inspect
     * @return array Schema information
     */
    public function getNodeSchema(string $label): array
    {
        // Get sample properties from nodes
        $propertiesQuery = "MATCH (n:`{$label}`) RETURN keys(n) as properties LIMIT 100";
        $propertiesResult = $this->connection->select($propertiesQuery);
        
        $allProperties = collect($propertiesResult)
            ->pluck('properties')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Get constraints for this label
        $constraintsQuery = "CALL db.constraints() YIELD name, type, labelsOrTypes, properties WHERE ? IN labelsOrTypes RETURN name, type, properties";
        $constraints = $this->connection->select($constraintsQuery, [$label]);

        // Get indexes for this label
        $indexesQuery = "CALL db.indexes() YIELD name, type, labelsOrTypes, properties WHERE ? IN labelsOrTypes RETURN name, type, properties";
        $indexes = $this->connection->select($indexesQuery, [$label]);

        return [
            'label' => $label,
            'properties' => $allProperties,
            'constraints' => $constraints,
            'indexes' => $indexes,
        ];
    }

    /**
     * Create a full-text index
     *
     * Creates a full-text search index for the specified node labels and properties.
     *
     * @param string $indexName Name of the index
     * @param array $nodeLabels Node labels to include
     * @param array $properties Properties to index
     * @return void
     */
    public function createFullTextIndex(string $indexName, array $nodeLabels, array $properties): void
    {
        $nodeLabelsStr = "'" . implode("', '", $nodeLabels) . "'";
        $propertiesStr = "'" . implode("', '", $properties) . "'";
        
        $query = "CALL db.index.fulltext.createNodeIndex('{$indexName}', [{$nodeLabelsStr}], [{$propertiesStr}])";
        $this->connection->statement($query);
    }

    /**
     * Drop a full-text index
     *
     * Removes a full-text search index from the database.
     *
     * @param string $indexName Name of the index to drop
     * @return void
     */
    public function dropFullTextIndex(string $indexName): void
    {
        $query = "CALL db.index.fulltext.drop('{$indexName}')";
        $this->connection->statement($query);
    }

    /**
     * Create a graph projection for analytics
     *
     * Creates a virtual graph projection that can be used for graph algorithms.
     *
     * @param string $projectionName Name of the projection
     * @param array $nodeLabels Node labels to include
     * @param array $relationships Relationship types to include
     * @param array $config Additional configuration options
     * @return void
     */
    public function createGraphProjection(string $projectionName, array $nodeLabels, array $relationships = [], array $config = []): void
    {
        $nodeConfig = collect($nodeLabels)->mapWithKeys(function ($label) {
            return [$label => ['label' => $label]];
        })->toArray();

        $relationshipConfig = collect($relationships)->mapWithKeys(function ($rel) {
            return [$rel => ['type' => $rel]];
        })->toArray();

        $projectionConfig = array_merge([
            'nodeProjection' => $nodeConfig,
            'relationshipProjection' => $relationshipConfig,
        ], $config);

        $configJson = json_encode($projectionConfig);
        $query = "CALL gds.graph.project('{$projectionName}', {$configJson})";
        
        $this->connection->statement($query);
    }

    /**
     * Drop a graph projection
     *
     * Removes a graph projection from memory.
     *
     * @param string $projectionName Name of the projection to drop
     * @return void
     */
    public function dropGraphProjection(string $projectionName): void
    {
        $query = "CALL gds.graph.drop('{$projectionName}')";
        $this->connection->statement($query);
    }

    /**
     * Create a new Neo4j blueprint instance
     *
     * @param string $label Node label name
     * @param Closure|null $callback Optional closure to configure the blueprint
     * @return Neo4jBlueprint Blueprint instance
     */
    protected function createNodeBlueprint(string $label, Closure $callback = null): Neo4jBlueprint
    {
        $blueprint = new Neo4jBlueprint($label);
        
        if ($callback) {
            $callback($blueprint);
        }
        
        return $blueprint;
    }

    /**
     * Execute the blueprint to build the schema
     *
     * Processes all commands in the blueprint and executes them against
     * the Neo4j database.
     *
     * @param Neo4jBlueprint $blueprint Blueprint to execute
     * @return void
     */
    protected function build(Neo4jBlueprint $blueprint): void
    {
        $statements = $blueprint->toSql($this->connection, $this->grammar);
        
        foreach ($statements as $statement) {
            $this->connection->statement($statement);
        }
    }

    /**
     * Get database statistics
     *
     * Returns general statistics about the Neo4j database including
     * node count, relationship count, and schema information.
     *
     * @return array Database statistics
     */
    public function getDatabaseStatistics(): array
    {
        $stats = [];
        
        // Get node count
        $nodeCountResult = $this->connection->select("MATCH (n) RETURN count(n) as nodeCount");
        $stats['nodeCount'] = $nodeCountResult[0]['nodeCount'] ?? 0;
        
        // Get relationship count
        $relCountResult = $this->connection->select("MATCH ()-[r]->() RETURN count(r) as relationshipCount");
        $stats['relationshipCount'] = $relCountResult[0]['relationshipCount'] ?? 0;
        
        // Get label count
        $stats['nodeLabels'] = count($this->getNodeLabels());
        
        // Get relationship type count
        $stats['relationshipTypes'] = count($this->getRelationshipTypes());
        
        // Get constraint count
        $stats['constraints'] = count($this->getConstraints());
        
        // Get index count
        $stats['indexes'] = count($this->getIndexes());
        
        return $stats;
    }
}