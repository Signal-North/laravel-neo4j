<?php

namespace SignalNorth\LaravelNeo4j\Database\Query\Processors;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\Path;
use DateTimeInterface;

/**
 * Neo4j Query Processor
 *
 * Processes results from Neo4j Cypher queries and transforms them into
 * Laravel-compatible formats. Handles Neo4j-specific data types and
 * result structures for seamless integration with Laravel's ORM.
 *
 * @pattern Strategy Pattern - Implements Neo4j-specific result processing
 * @pattern Adapter Pattern - Adapts Neo4j results to Laravel expectations
 * @package App\Database\Query\Processors
 * @since 1.0.0
 * @security Ensures safe data transformation and type conversion
 */
class Neo4jProcessor extends Processor
{
    /**
     * Process the results of a "select" query
     *
     * Transforms Neo4j query results into a standardised array format
     * that Laravel applications can consume. Handles nested objects,
     * nodes, relationships, and other Neo4j-specific data types.
     *
     * @param Builder $query Query builder instance
     * @param array $results Raw results from Neo4j
     * @return array Processed results
     */
    public function processSelect(Builder $query, $results): array
    {
        if (empty($results)) {
            return [];
        }

        return collect($results)->map(function ($record) {
            return $this->processRecord($record);
        })->toArray();
    }

    /**
     * Process the results of an "insert" query
     *
     * Handles the results from Neo4j CREATE operations, extracting
     * relevant information about created nodes and their properties.
     *
     * @param Builder $query Query builder instance
     * @param array $results Results from Neo4j CREATE operation
     * @return bool Success status
     */
    public function processInsert(Builder $query, $results): bool
    {
        return !empty($results);
    }

    /**
     * Process the results of an "update" query
     *
     * Processes results from Neo4j SET operations, returning the number
     * of affected records based on the operation statistics.
     *
     * @param Builder $query Query builder instance
     * @param array $results Results from Neo4j SET operation
     * @return int Number of affected records
     */
    public function processUpdate(Builder $query, $results): int
    {
        // For update operations, we typically return the count of modified records
        // This should be handled in the connection layer via result summary
        return count($results);
    }

    /**
     * Process the results of a "delete" query
     *
     * Processes results from Neo4j DELETE operations, returning the number
     * of deleted records based on the operation statistics.
     *
     * @param Builder $query Query builder instance
     * @param array $results Results from Neo4j DELETE operation
     * @return int Number of deleted records
     */
    public function processDelete(Builder $query, $results): int
    {
        // For delete operations, we typically return the count of deleted records
        // This should be handled in the connection layer via result summary
        return count($results);
    }

    /**
     * Process an individual record from Neo4j
     *
     * Converts a single Neo4j record into a PHP array, handling
     * all Neo4j-specific data types and nested structures.
     *
     * @param mixed $record Single record from Neo4j result
     * @return array Processed record data
     */
    protected function processRecord($record): array
    {
        if (is_array($record)) {
            return collect($record)->mapWithKeys(function ($value, $key) {
                return [$key => $this->convertNeo4jValue($value)];
            })->toArray();
        }

        if (is_object($record) && method_exists($record, 'toArray')) {
            return $this->convertNeo4jValue($record);
        }

        return ['data' => $this->convertNeo4jValue($record)];
    }

    /**
     * Convert Neo4j-specific values to PHP equivalents
     *
     * Recursively processes Neo4j data types (Nodes, Relationships, Lists, Maps)
     * and converts them to standard PHP arrays and values that Laravel can work with.
     *
     * @param mixed $value Value to convert
     * @return mixed Converted PHP value
     */
    protected function convertNeo4jValue($value)
    {
        // Handle null values
        if (is_null($value)) {
            return null;
        }

        // Handle Neo4j Node objects
        if ($value instanceof Node) {
            return $this->convertNode($value);
        }

        // Handle Neo4j Relationship objects
        if ($value instanceof Relationship) {
            return $this->convertRelationship($value);
        }

        // Handle Neo4j Path objects
        if ($value instanceof Path) {
            return $this->convertPath($value);
        }

        // Handle Neo4j CypherMap (similar to associative arrays)
        if ($value instanceof CypherMap) {
            return $this->convertCypherMap($value);
        }

        // Handle Neo4j CypherList (similar to indexed arrays)
        if ($value instanceof CypherList) {
            return $this->convertCypherList($value);
        }

        // Handle DateTime objects
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        // Handle standard arrays
        if (is_array($value)) {
            return collect($value)->map(function ($item) {
                return $this->convertNeo4jValue($item);
            })->toArray();
        }

        // Handle objects with toArray method
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $this->convertNeo4jValue($value->toArray());
        }

        // Return primitive values as-is
        return $value;
    }

    /**
     * Convert a Neo4j Node to an array
     *
     * Extracts the node's properties and metadata into a flat array
     * structure suitable for Laravel model hydration.
     *
     * @param Node $node Neo4j Node object
     * @return array Node data as array
     */
    protected function convertNode(Node $node): array
    {
        $data = [
            '_neo4j_id' => $node->id(),
            '_neo4j_labels' => $node->labels()->toArray(),
        ];

        // Add all node properties
        foreach ($node->properties() as $key => $value) {
            $data[$key] = $this->convertNeo4jValue($value);
        }

        return $data;
    }

    /**
     * Convert a Neo4j Relationship to an array
     *
     * Extracts the relationship's properties, type, and connected node IDs
     * into an array structure.
     *
     * @param Relationship $relationship Neo4j Relationship object
     * @return array Relationship data as array
     */
    protected function convertRelationship(Relationship $relationship): array
    {
        $data = [
            '_neo4j_id' => $relationship->id(),
            '_neo4j_type' => $relationship->type(),
            '_neo4j_start_id' => $relationship->startNodeId(),
            '_neo4j_end_id' => $relationship->endNodeId(),
        ];

        // Add all relationship properties
        foreach ($relationship->properties() as $key => $value) {
            $data[$key] = $this->convertNeo4jValue($value);
        }

        return $data;
    }

    /**
     * Convert a Neo4j Path to an array
     *
     * Extracts the path's nodes and relationships into a structured array
     * representing the graph traversal path.
     *
     * @param Path $path Neo4j Path object
     * @return array Path data as array
     */
    protected function convertPath(Path $path): array
    {
        return [
            '_neo4j_type' => 'path',
            'nodes' => collect($path->nodes())->map(function ($node) {
                return $this->convertNode($node);
            })->toArray(),
            'relationships' => collect($path->relationships())->map(function ($relationship) {
                return $this->convertRelationship($relationship);
            })->toArray(),
        ];
    }

    /**
     * Convert a Neo4j CypherMap to an array
     *
     * Recursively converts all values in the map to their PHP equivalents.
     *
     * @param CypherMap $map Neo4j CypherMap object
     * @return array Map data as array
     */
    protected function convertCypherMap(CypherMap $map): array
    {
        $result = [];
        
        foreach ($map as $key => $value) {
            $result[$key] = $this->convertNeo4jValue($value);
        }

        return $result;
    }

    /**
     * Convert a Neo4j CypherList to an array
     *
     * Recursively converts all values in the list to their PHP equivalents.
     *
     * @param CypherList $list Neo4j CypherList object
     * @return array List data as array
     */
    protected function convertCypherList(CypherList $list): array
    {
        $result = [];
        
        foreach ($list as $value) {
            $result[] = $this->convertNeo4jValue($value);
        }

        return $result;
    }

    /**
     * Extract column information from processed results
     *
     * Analyses the processed results to determine available columns
     * and their data types for query optimisation.
     *
     * @param array $results Processed query results
     * @return array Column information
     */
    public function processColumns($results): array
    {
        if (!is_array($results) || empty($results)) {
            return [];
        }

        $firstRecord = reset($results);
        
        if (!is_array($firstRecord)) {
            return [];
        }

        return collect($firstRecord)->keys()->map(function ($column) use ($firstRecord) {
            return [
                'name' => $column,
                'type' => $this->inferColumnType($firstRecord[$column]),
            ];
        })->toArray();
    }

    /**
     * Infer the data type of a column value
     *
     * Analyses a value to determine its most appropriate data type
     * for schema and query optimisation purposes.
     *
     * @param mixed $value Column value to analyse
     * @return string Inferred data type
     */
    protected function inferColumnType($value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_string($value)) {
            return 'string';
        }

        if (is_array($value)) {
            return isset($value['_neo4j_type']) ? $value['_neo4j_type'] : 'array';
        }

        return 'mixed';
    }

    /**
     * Process aggregation results
     *
     * Handles results from aggregate functions (COUNT, SUM, AVG, etc.)
     * and formats them appropriately for Laravel consumption.
     *
     * @param Builder $query Query builder instance
     * @param array $results Aggregation results
     * @return mixed Processed aggregation result
     */
    public function processAggregate(Builder $query, array $results)
    {
        if (empty($results)) {
            return null;
        }

        $result = reset($results);
        
        if (is_array($result) && count($result) === 1) {
            return reset($result);
        }

        return $result;
    }
}