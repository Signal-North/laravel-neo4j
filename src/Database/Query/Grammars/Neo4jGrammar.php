<?php

namespace SignalNorth\LaravelNeo4j\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Str;

/**
 * Neo4j Query Grammar
 *
 * Compiles Laravel query builder statements into Cypher queries for Neo4j.
 * Adapts SQL-style operations to their Cypher equivalents while maintaining
 * Laravel's query builder interface compatibility.
 *
 * @pattern Strategy Pattern - Implements grammar-specific query compilation
 * @pattern Adapter Pattern - Adapts SQL operations to Cypher syntax
 * @package SignalNorth\LaravelNeo4j\Database\Query\Grammars
 * @since 1.0.0
 * @security Ensures proper parameter binding and query escaping
 */
class Neo4jGrammar extends Grammar
{
    /**
     * The grammar specific operators for Neo4j
     *
     * @var array
     */
    protected $operators = [
        '=', '<>', '!=', '<', '>', '<=', '>=',
        'LIKE', 'NOT LIKE', 'ILIKE',
        'CONTAINS', 'NOT CONTAINS',
        'STARTS WITH', 'NOT STARTS WITH',
        'ENDS WITH', 'NOT ENDS WITH',
        'IN', 'NOT IN',
        'IS NULL', 'IS NOT NULL',
        'REGEX', 'NOT REGEX'
    ];

    /**
     * Compile a select query into Cypher
     *
     * Converts Laravel's query builder select statement into a Cypher MATCH/RETURN query.
     * Maps table names to node labels and implements basic graph traversal patterns.
     *
     * @param Builder $query Query builder instance
     * @return string Compiled Cypher query
     */
    public function compileSelect(Builder $query): string
    {
        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        return trim($this->concatenate([
            $this->compileMatch($query),
            $this->compileWheres($query),
            $this->compileGroups($query, $query->groups),
            $this->compileHavings($query, $query->havings),
            $this->compileOrders($query, $query->orders),
            $this->compileLimit($query, $query->limit),
            $this->compileOffset($query, $query->offset),
            $this->compileReturn($query),
        ]));
    }

    /**
     * Compile the MATCH clause for Neo4j
     *
     * Creates a MATCH clause that corresponds to the FROM clause in SQL.
     * Maps table names to node labels and handles basic relationships.
     *
     * @param Builder $query Query builder instance
     * @return string MATCH clause
     */
    protected function compileMatch(Builder $query): string
    {
        $table = $this->wrapTable($query->from);
        $nodeVariable = $this->getNodeVariable($query->from);
        
        return "MATCH ({$nodeVariable}:{$table})";
    }

    /**
     * Compile the RETURN clause for Neo4j
     *
     * Creates a RETURN clause that corresponds to the SELECT clause in SQL.
     * Handles column selection and aliasing for Neo4j nodes.
     *
     * @param Builder $query Query builder instance
     * @return string RETURN clause
     */
    protected function compileReturn(Builder $query): string
    {
        $nodeVariable = $this->getNodeVariable($query->from);
        
        if ($query->columns === ['*']) {
            return "RETURN {$nodeVariable}";
        }
        
        $columns = collect($query->columns)->map(function ($column) use ($nodeVariable) {
            if ($column === '*') {
                return $nodeVariable;
            }
            
            if (Str::contains($column, ' as ')) {
                [$field, $alias] = explode(' as ', $column);
                return "{$nodeVariable}.{$this->wrap($field)} AS {$this->wrap($alias)}";
            }
            
            return "{$nodeVariable}.{$this->wrap($column)}";
        })->implode(', ');
        
        return "RETURN {$columns}";
    }

    /**
     * Compile an insert statement into Cypher
     *
     * Creates a CREATE statement for inserting new nodes into Neo4j.
     * Maps SQL INSERT to Cypher CREATE with proper parameter binding.
     *
     * @param Builder $query Query builder instance
     * @param array $values Values to insert
     * @return string Compiled CREATE query
     */
    public function compileInsert(Builder $query, array $values): string
    {
        if (empty($values)) {
            return '';
        }

        $table = $this->wrapTable($query->from);
        
        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $nodes = [];
        foreach ($values as $record) {
            $properties = $this->compileInsertProperties($record);
            $nodes[] = "({$table}{$properties})";
        }

        return 'CREATE ' . implode(', ', $nodes) . ' RETURN *';
    }

    /**
     * Compile an update statement into Cypher
     *
     * Creates a MATCH...SET statement for updating existing nodes in Neo4j.
     * Maps SQL UPDATE to Cypher MATCH/SET pattern.
     *
     * @param Builder $query Query builder instance
     * @param array $values Values to update
     * @return string Compiled update query
     */
    public function compileUpdate(Builder $query, array $values): string
    {
        $table = $this->wrapTable($query->from);
        $nodeVariable = $this->getNodeVariable($query->from);
        
        $match = "MATCH ({$nodeVariable}:{$table})";
        $where = $this->compileWheres($query);
        $set = $this->compileUpdateSet($nodeVariable, $values);
        
        return trim("{$match} {$where} {$set} RETURN {$nodeVariable}");
    }

    /**
     * Compile a delete statement into Cypher
     *
     * Creates a MATCH...DELETE statement for removing nodes from Neo4j.
     * Maps SQL DELETE to Cypher MATCH/DELETE pattern.
     *
     * @param Builder $query Query builder instance
     * @return string Compiled delete query
     */
    public function compileDelete(Builder $query): string
    {
        $table = $this->wrapTable($query->from);
        $nodeVariable = $this->getNodeVariable($query->from);
        
        $match = "MATCH ({$nodeVariable}:{$table})";
        $where = $this->compileWheres($query);
        $delete = "DELETE {$nodeVariable}";
        
        return trim("{$match} {$where} {$delete}");
    }

    /**
     * Compile the WHERE clauses for Neo4j
     *
     * Converts SQL WHERE conditions to Cypher WHERE clauses.
     * Handles property comparisons and logical operators.
     *
     * @param Builder $query Query builder instance
     * @return string WHERE clause
     */
    public function compileWheres(Builder $query): string
    {
        if (is_null($query->wheres)) {
            return '';
        }

        $nodeVariable = $this->getNodeVariable($query->from);
        
        $sql = $this->compileWheresToArray($query);

        if (count($sql) > 0) {
            $conditions = implode(' ', $sql);
            $conditions = $this->adaptWhereForNeo4j($conditions, $nodeVariable);
            return 'WHERE ' . $this->removeLeadingBoolean($conditions);
        }

        return '';
    }

    /**
     * Compile ORDER BY clause for Neo4j
     *
     * @param Builder $query Query builder instance
     * @param array $orders Orders array from query builder
     * @return string ORDER BY clause
     */
    protected function compileOrders(Builder $query, $orders): string
    {
        if (!$orders || empty($orders)) {
            return '';
        }

        $nodeVariable = $this->getNodeVariable($query->from);
        
        $orderClauses = collect($orders)->map(function ($order) use ($nodeVariable) {
            $column = $order['column'];
            $direction = strtoupper($order['direction']);
            
            return "{$nodeVariable}.{$this->wrap($column)} {$direction}";
        })->implode(', ');

        return "ORDER BY {$orderClauses}";
    }

    /**
     * Compile LIMIT clause for Neo4j
     *
     * @param Builder $query Query builder instance
     * @param int $limit Limit value
     * @return string LIMIT clause
     */
    protected function compileLimit(Builder $query, $limit): string
    {
        if ($limit) {
            return "LIMIT {$limit}";
        }

        return '';
    }

    /**
     * Compile SKIP clause for Neo4j (equivalent to OFFSET)
     *
     * @param Builder $query Query builder instance
     * @param int $offset Offset value
     * @return string SKIP clause
     */
    protected function compileOffset(Builder $query, $offset): string
    {
        if ($offset) {
            return "SKIP {$offset}";
        }

        return '';
    }

    /**
     * Get node variable name from table name
     *
     * @param string $table Table/label name
     * @return string Node variable name
     */
    protected function getNodeVariable(string $table): string
    {
        return strtolower(Str::singular($table));
    }

    /**
     * Compile properties for insert statement
     *
     * @param array $values Property values
     * @return string Properties string
     */
    protected function compileInsertProperties(array $values): string
    {
        if (empty($values)) {
            return '';
        }

        $properties = collect($values)->map(function ($value, $key) {
            return "{$this->wrap($key)}: {$this->parameter($value)}";
        })->implode(', ');

        return " {{$properties}}";
    }

    /**
     * Compile SET clause for update statement
     *
     * @param string $nodeVariable Node variable name
     * @param array $values Values to set
     * @return string SET clause
     */
    protected function compileUpdateSet(string $nodeVariable, array $values): string
    {
        $sets = collect($values)->map(function ($value, $key) use ($nodeVariable) {
            return "{$nodeVariable}.{$this->wrap($key)} = {$this->parameter($value)}";
        })->implode(', ');

        return "SET {$sets}";
    }

    /**
     * Adapt WHERE conditions for Neo4j syntax
     *
     * @param string $conditions WHERE conditions
     * @param string $nodeVariable Node variable name
     * @return string Adapted conditions
     */
    protected function adaptWhereForNeo4j(string $conditions, string $nodeVariable): string
    {
        // Replace column references with node property references
        $conditions = preg_replace('/`([^`]+)`/', "{$nodeVariable}.`$1`", $conditions);
        
        // Convert SQL operators to Cypher equivalents
        $conditions = str_replace(['<>', '!='], ['<>', '<>'], $conditions);
        
        return $conditions;
    }

    /**
     * Wrap a table in keyword identifiers
     *
     * @param string $table Table name
     * @param string|null $prefix Table prefix (optional)
     * @return string Wrapped table name
     */
    public function wrapTable($table, $prefix = null): string
    {
        $tablePrefix = $prefix ?? $this->tablePrefix;
        return $this->wrap($tablePrefix . $table);
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
}