<?php

namespace SignalNorth\LaravelNeo4j\Database\Schema;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Fluent;

/**
 * Neo4j Schema Blueprint
 *
 * Extends Laravel's Blueprint class to add Neo4j-specific schema operations.
 * Provides methods for creating nodes, relationships, constraints, and indexes
 * that are specific to graph database structures.
 *
 * @pattern Builder Pattern - Fluent interface for constructing schema operations
 * @pattern Strategy Pattern - Neo4j-specific implementation of schema building
 * @package App\Database\Schema
 * @since 1.0.0
 * @security Validates schema operations and ensures data integrity
 */
class Neo4jBlueprint extends Blueprint
{
    /**
     * Create a new node label with optional properties
     *
     * Defines a new node type in the graph database with initial properties
     * and constraints. This is equivalent to creating a table in relational databases.
     *
     * @param string $label Node label name
     * @param array $properties Optional initial properties
     * @return Fluent Command object for further configuration
     */
    public function createNode(string $label, array $properties = []): Fluent
    {
        return $this->addCommand('createNode', [
            'label' => $label,
            'properties' => $properties,
        ]);
    }

    /**
     * Create a relationship type between two node labels
     *
     * Defines a new relationship type that can connect nodes of specified labels.
     * Relationships in Neo4j can have properties and directionality.
     *
     * @param string $from Source node label
     * @param string $to Target node label
     * @param string $relationship Relationship type name
     * @param array $properties Optional relationship properties
     * @return Fluent Command object for further configuration
     */
    public function createRelationship(string $from, string $to, string $relationship, array $properties = []): Fluent
    {
        return $this->addCommand('createRelationship', [
            'from' => $from,
            'to' => $to,
            'relationship' => $relationship,
            'properties' => $properties,
        ]);
    }

    /**
     * Add a unique constraint on node property
     *
     * Ensures that the specified property has unique values across all nodes
     * of the given label. Equivalent to a unique index in relational databases.
     *
     * @param string|array $columns Property name(s)
     * @param string|null $name Optional constraint name
     * @return Fluent Command object for further configuration
     */
    public function nodeUnique($columns, $name = null): Fluent
    {
        return $this->indexCommand('unique', $columns, $name);
    }

    /**
     * Add an index on node property
     *
     * Creates an index to improve query performance for the specified property.
     * Indexes in Neo4j are used to speed up node lookups and traversals.
     *
     * @param string|array $columns Property name(s)
     * @param string|null $name Optional index name
     * @return Fluent Command object for further configuration
     */
    public function nodeIndex($columns, $name = null): Fluent
    {
        return $this->indexCommand('index', $columns, $name);
    }

    /**
     * Add a node key constraint (composite unique constraint)
     *
     * Creates a node key constraint that ensures uniqueness across multiple
     * properties. This is equivalent to a primary key in relational databases.
     *
     * @param string|array $columns Property name(s)
     * @param string|null $name Optional constraint name
     * @return Fluent Command object for further configuration
     */
    public function nodeKey($columns, $name = null): Fluent
    {
        return $this->indexCommand('primary', $columns, $name);
    }

    /**
     * Add an existence constraint on node property
     *
     * Ensures that all nodes of the specified label have the given property.
     * This prevents nodes from being created without required properties.
     *
     * @param string $column Property name
     * @param string|null $name Optional constraint name
     * @return Fluent Command object for further configuration
     */
    public function nodeExists(string $column, $name = null): Fluent
    {
        return $this->addCommand('nodeExists', [
            'column' => $column,
            'index' => $name,
        ]);
    }

    /**
     * Add a property type constraint
     *
     * Ensures that the specified property always contains values of the given type.
     * Helps maintain data consistency across the graph.
     *
     * @param string $column Property name
     * @param string $type Expected data type (string, integer, float, boolean, etc.)
     * @param string|null $name Optional constraint name
     * @return Fluent Command object for further configuration
     */
    public function nodePropertyType(string $column, string $type, $name = null): Fluent
    {
        return $this->addCommand('nodePropertyType', [
            'column' => $column,
            'type' => $type,
            'index' => $name,
        ]);
    }

    /**
     * Add a range constraint on node property
     *
     * Ensures that numeric or date properties fall within specified ranges.
     * Useful for validating data integrity at the database level.
     *
     * @param string $column Property name
     * @param mixed $min Minimum value
     * @param mixed $max Maximum value
     * @param string|null $name Optional constraint name
     * @return Fluent Command object for further configuration
     */
    public function nodePropertyRange(string $column, $min, $max, $name = null): Fluent
    {
        return $this->addCommand('nodePropertyRange', [
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'index' => $name,
        ]);
    }

    /**
     * Add a full-text index on node properties
     *
     * Creates a full-text search index for text-based properties.
     * Enables efficient text searching across node properties.
     *
     * @param array $columns Property names to include in the index
     * @param string $name Index name
     * @return Fluent Command object for further configuration
     */
    public function fullTextIndex(array $columns, string $name): Fluent
    {
        return $this->addCommand('fullTextIndex', [
            'columns' => $columns,
            'index' => $name,
        ]);
    }

    /**
     * Create a graph projection for analytics
     *
     * Defines a virtual graph projection that can be used for graph algorithms
     * and analytics operations without affecting the main graph structure.
     *
     * @param string $projectionName Name of the projection
     * @param array $nodeLabels Node labels to include
     * @param array $relationships Relationship types to include
     * @return Fluent Command object for further configuration
     */
    public function graphProjection(string $projectionName, array $nodeLabels, array $relationships = []): Fluent
    {
        return $this->addCommand('graphProjection', [
            'name' => $projectionName,
            'nodeLabels' => $nodeLabels,
            'relationships' => $relationships,
        ]);
    }

    /**
     * Drop a node label and all associated constraints
     *
     * Removes a node label from the database schema along with all constraints,
     * indexes, and optionally the nodes themselves.
     *
     * @param string $label Node label to drop
     * @param bool $deleteNodes Whether to delete all nodes with this label
     * @return Fluent Command object for further configuration
     */
    public function dropNodeLabel(string $label, bool $deleteNodes = false): Fluent
    {
        return $this->addCommand('dropNodeLabel', [
            'label' => $label,
            'deleteNodes' => $deleteNodes,
        ]);
    }

    /**
     * Drop a relationship type
     *
     * Removes a relationship type from the database schema, optionally
     * deleting all existing relationships of that type.
     *
     * @param string $relationshipType Relationship type to drop
     * @param bool $deleteRelationships Whether to delete all relationships of this type
     * @return Fluent Command object for further configuration
     */
    public function dropRelationshipType(string $relationshipType, bool $deleteRelationships = false): Fluent
    {
        return $this->addCommand('dropRelationshipType', [
            'type' => $relationshipType,
            'deleteRelationships' => $deleteRelationships,
        ]);
    }

    /**
     * Add a spatial index for geographic data
     *
     * Creates a spatial index for properties containing geographic coordinates.
     * Enables efficient spatial queries and geographic analysis.
     *
     * @param string $column Property containing spatial data
     * @param string $name Index name
     * @param string $type Spatial index type (point, polygon, etc.)
     * @return Fluent Command object for further configuration
     */
    public function spatialIndex(string $column, string $name, string $type = 'point'): Fluent
    {
        return $this->addCommand('spatialIndex', [
            'column' => $column,
            'index' => $name,
            'type' => $type,
        ]);
    }

    /**
     * Create a temporal index for date/time properties
     *
     * Creates an index optimised for temporal data queries.
     * Improves performance of date-based filtering and sorting.
     *
     * @param string $column Property containing temporal data
     * @param string $name Index name
     * @return Fluent Command object for further configuration
     */
    public function temporalIndex(string $column, string $name): Fluent
    {
        return $this->addCommand('temporalIndex', [
            'column' => $column,
            'index' => $name,
        ]);
    }

    /**
     * Add a composite constraint across multiple properties
     *
     * Creates a constraint that validates relationships between multiple
     * properties on the same node.
     *
     * @param array $columns Properties to include in the constraint
     * @param string $expression Constraint expression
     * @param string|null $name Optional constraint name
     * @return Fluent Command object for further configuration
     */
    public function compositeConstraint(array $columns, string $expression, $name = null): Fluent
    {
        return $this->addCommand('compositeConstraint', [
            'columns' => $columns,
            'expression' => $expression,
            'index' => $name,
        ]);
    }

    /**
     * Define a derived property based on other properties
     *
     * Creates a virtual property that is computed from other properties.
     * Useful for creating calculated fields and data transformations.
     *
     * @param string $name Property name
     * @param string $expression Computation expression
     * @return Fluent Command object for further configuration
     */
    public function derivedProperty(string $name, string $expression): Fluent
    {
        return $this->addCommand('derivedProperty', [
            'name' => $name,
            'expression' => $expression,
        ]);
    }

    /**
     * Override the string method to handle graph schema operations
     *
     * Ensures that graph-specific operations are properly handled
     * when converting the blueprint to string representation.
     *
     * @return string String representation of the blueprint
     */
    public function __toString(): string
    {
        return sprintf(
            'Neo4j Blueprint for label: %s with %d commands',
            $this->getTable(),
            count($this->commands)
        );
    }

    /**
     * Get statistics about the current blueprint
     *
     * Returns information about the number and types of operations
     * defined in this blueprint for debugging and monitoring.
     *
     * @return array Blueprint statistics
     */
    public function getStatistics(): array
    {
        $commandTypes = collect($this->commands)->groupBy('name')->map->count();
        
        return [
            'label' => $this->getTable(),
            'total_commands' => count($this->commands),
            'command_types' => $commandTypes->toArray(),
            'has_constraints' => $commandTypes->has('unique') || $commandTypes->has('primary'),
            'has_indexes' => $commandTypes->has('index') || $commandTypes->has('fullTextIndex'),
        ];
    }
}