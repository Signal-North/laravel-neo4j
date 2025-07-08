<?php

namespace SignalNorth\LaravelNeo4j\Database\Migrations;

use SignalNorth\LaravelNeo4j\Database\Neo4jConnection;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

/**
 * Neo4j Migration Repository
 *
 * Manages migration state for Neo4j database by storing migration records
 * as nodes in the graph database. Implements Laravel's migration repository
 * interface while adapting it for graph database storage patterns.
 *
 * @pattern Repository Pattern - Abstracts migration data persistence
 * @pattern Adapter Pattern - Adapts relational migration concepts to graph storage
 * @package App\Database\Migrations
 * @since 1.0.0
 * @security Ensures secure migration tracking and prevents tampering
 */
class Neo4jMigrationRepository implements MigrationRepositoryInterface
{
    /**
     * The Neo4j database connection instance
     *
     * @var Neo4jConnection
     */
    protected $connection;

    /**
     * The name of the migration node label
     *
     * @var string
     */
    protected $label;

    /**
     * Create a new Neo4j migration repository instance
     *
     * @param Neo4jConnection $connection Neo4j connection instance
     * @param string $label Migration node label name
     */
    public function __construct(Neo4jConnection $connection, string $label = 'Migration')
    {
        $this->connection = $connection;
        $this->label = $label;
    }

    /**
     * Get the completed migrations
     *
     * Retrieves all completed migrations from the Neo4j database,
     * returning them in the order they were executed.
     *
     * @return array List of completed migration names
     */
    public function getRan(): array
    {
        $query = "MATCH (m:`{$this->label}`) RETURN m.migration as migration ORDER BY m.batch, m.migration";
        $results = $this->connection->select($query);
        
        return collect($results)->pluck('migration')->toArray();
    }

    /**
     * Get list of migrations by batch number
     *
     * Retrieves migrations that were executed in a specific batch,
     * useful for rollback operations.
     *
     * @param int $batch Batch number
     * @return array List of migrations in the batch
     */
    public function getMigrations($batch): array
    {
        $query = "MATCH (m:`{$this->label}`) WHERE m.batch = ? RETURN m.migration as migration ORDER BY m.migration";
        $results = $this->connection->select($query, [$batch]);
        
        return collect($results)->pluck('migration')->toArray();
    }

    /**
     * Get the last migration batch
     *
     * Returns all migrations from the most recent batch,
     * typically used for rollback operations.
     *
     * @return array List of migrations from the last batch
     */
    public function getLast(): array
    {
        $query = "MATCH (m:`{$this->label}`) WITH max(m.batch) as lastBatch MATCH (m2:`{$this->label}`) WHERE m2.batch = lastBatch RETURN m2.migration as migration ORDER BY m2.migration";
        $results = $this->connection->select($query);
        
        return collect($results)->pluck('migration')->toArray();
    }

    /**
     * Get the completed migrations with their batch numbers
     *
     * Returns detailed information about completed migrations including
     * their batch numbers and execution timestamps.
     *
     * @return array List of migration details
     */
    public function getMigrationBatches(): array
    {
        $query = "MATCH (m:`{$this->label}`) RETURN m.migration as migration, m.batch as batch ORDER BY m.batch, m.migration";
        $results = $this->connection->select($query);
        
        return collect($results)->mapWithKeys(function ($result) {
            return [$result['migration'] => $result['batch']];
        })->toArray();
    }

    /**
     * Log that a migration was run
     *
     * Creates a new migration node in the Neo4j database to record
     * that a migration has been successfully executed.
     *
     * @param string $file Migration file name
     * @param int $batch Batch number
     * @return void
     */
    public function log($file, $batch): void
    {
        $timestamp = Carbon::now()->toISOString();
        
        $query = "CREATE (m:`{$this->label}` {migration: ?, batch: ?, executed_at: ?}) RETURN m";
        $this->connection->statement($query, [$file, $batch, $timestamp]);
    }

    /**
     * Remove a migration from the log
     *
     * Deletes a migration node from the Neo4j database, typically
     * used during rollback operations.
     *
     * @param object $migration Migration object with file property
     * @return void
     */
    public function delete($migration): void
    {
        $migrationName = is_object($migration) ? $migration->file : $migration;
        
        $query = "MATCH (m:`{$this->label}` {migration: ?}) DELETE m";
        $this->connection->statement($query, [$migrationName]);
    }

    /**
     * Get the next migration batch number
     *
     * Calculates the next batch number for new migrations by finding
     * the highest existing batch number and incrementing it.
     *
     * @return int Next batch number
     */
    public function getNextBatchNumber(): int
    {
        $query = "MATCH (m:`{$this->label}`) RETURN max(m.batch) as maxBatch";
        $results = $this->connection->select($query);
        
        $maxBatch = $results[0]['maxBatch'] ?? 0;
        
        return $maxBatch + 1;
    }

    /**
     * Get the last migration batch number
     *
     * Returns the batch number of the most recently executed migrations.
     *
     * @return int Last batch number
     */
    public function getLastBatchNumber(): int
    {
        $query = "MATCH (m:`{$this->label}`) RETURN max(m.batch) as maxBatch";
        $results = $this->connection->select($query);
        
        return $results[0]['maxBatch'] ?? 0;
    }

    /**
     * Create the migration repository data store
     *
     * Creates the necessary constraints and indexes for the migration
     * node label to ensure data integrity and performance.
     *
     * @return void
     */
    public function createRepository(): void
    {
        try {
            // Create unique constraint on migration name
            $uniqueConstraint = "CREATE CONSTRAINT {$this->label}_migration_unique IF NOT EXISTS FOR (m:`{$this->label}`) REQUIRE m.migration IS UNIQUE";
            $this->connection->statement($uniqueConstraint);
            
            // Create index on batch number for performance
            $batchIndex = "CREATE INDEX {$this->label}_batch_index IF NOT EXISTS FOR (m:`{$this->label}`) ON (m.batch)";
            $this->connection->statement($batchIndex);
            
            // Create index on execution timestamp
            $timestampIndex = "CREATE INDEX {$this->label}_timestamp_index IF NOT EXISTS FOR (m:`{$this->label}`) ON (m.executed_at)";
            $this->connection->statement($timestampIndex);
            
        } catch (QueryException $e) {
            // Constraints/indexes might already exist, which is fine
            if (!str_contains($e->getMessage(), 'already exists')) {
                throw $e;
            }
        }
    }

    /**
     * Determine if the migration repository exists
     *
     * Checks whether the migration repository has been properly set up
     * by looking for migration constraints and sample data.
     *
     * @return bool True if repository exists, false otherwise
     */
    public function repositoryExists(): bool
    {
        try {
            // Check if the migration label constraint exists
            $query = "CALL db.constraints() YIELD name, labelsOrTypes WHERE ? IN labelsOrTypes AND name CONTAINS 'migration' RETURN count(*) as constraintCount";
            $results = $this->connection->select($query, [$this->label]);
            
            return ($results[0]['constraintCount'] ?? 0) > 0;
        } catch (QueryException $e) {
            return false;
        }
    }

    /**
     * Delete the migration repository data store
     *
     * Removes all migration-related constraints, indexes, and data.
     * This is typically used during testing or complete resets.
     *
     * @return void
     */
    public function deleteRepository(): void
    {
        try {
            // Delete all migration nodes
            $deleteNodes = "MATCH (m:`{$this->label}`) DELETE m";
            $this->connection->statement($deleteNodes);
            
            // Drop constraints
            $dropConstraints = "CALL db.constraints() YIELD name, labelsOrTypes WHERE ? IN labelsOrTypes CALL db.dropConstraint(name) YIELD name as dropped RETURN dropped";
            $this->connection->statement($dropConstraints, [$this->label]);
            
            // Drop indexes
            $dropIndexes = "CALL db.indexes() YIELD name, labelsOrTypes WHERE ? IN labelsOrTypes CALL db.dropIndex(name) YIELD name as dropped RETURN dropped";
            $this->connection->statement($dropIndexes, [$this->label]);
            
        } catch (QueryException $e) {
            // Some operations might fail if items don't exist, which is acceptable
        }
    }

    /**
     * Get migration statistics
     *
     * Returns statistical information about the migration repository
     * including total migrations, batches, and timing information.
     *
     * @return array Migration statistics
     */
    public function getMigrationStatistics(): array
    {
        $totalQuery = "MATCH (m:`{$this->label}`) RETURN count(m) as total";
        $totalResult = $this->connection->select($totalQuery);
        $total = $totalResult[0]['total'] ?? 0;
        
        $batchQuery = "MATCH (m:`{$this->label}`) RETURN count(DISTINCT m.batch) as batches";
        $batchResult = $this->connection->select($batchQuery);
        $batches = $batchResult[0]['batches'] ?? 0;
        
        $latestQuery = "MATCH (m:`{$this->label}`) RETURN max(m.executed_at) as latest";
        $latestResult = $this->connection->select($latestQuery);
        $latest = $latestResult[0]['latest'] ?? null;
        
        return [
            'total_migrations' => $total,
            'total_batches' => $batches,
            'latest_execution' => $latest,
            'repository_label' => $this->label,
        ];
    }

    /**
     * Check if a specific migration has been run
     *
     * Determines whether a specific migration file has been executed
     * by checking for its existence in the migration repository.
     *
     * @param string $migration Migration file name
     * @return bool True if migration has been run, false otherwise
     */
    public function hasMigration(string $migration): bool
    {
        $query = "MATCH (m:`{$this->label}` {migration: ?}) RETURN count(m) > 0 as exists";
        $results = $this->connection->select($query, [$migration]);
        
        return $results[0]['exists'] ?? false;
    }

    /**
     * Get detailed information about a specific migration
     *
     * Returns comprehensive details about a specific migration including
     * execution time, batch number, and any associated metadata.
     *
     * @param string $migration Migration file name
     * @return array|null Migration details or null if not found
     */
    public function getMigrationDetails(string $migration): ?array
    {
        $query = "MATCH (m:`{$this->label}` {migration: ?}) RETURN m.migration as migration, m.batch as batch, m.executed_at as executed_at";
        $results = $this->connection->select($query, [$migration]);
        
        return $results[0] ?? null;
    }

    /**
     * Get all migrations with full details
     *
     * Returns comprehensive information about all migrations including
     * execution order, timing, and batch information.
     *
     * @return array List of migration details
     */
    public function getAllMigrations(): array
    {
        $query = "MATCH (m:`{$this->label}`) RETURN m.migration as migration, m.batch as batch, m.executed_at as executed_at ORDER BY m.batch, m.migration";
        return $this->connection->select($query);
    }

    /**
     * Get migrations by batch number (newer interface method)
     *
     * @param int|mixed $batch Batch number
     * @return array List of migrations in the batch
     */
    public function getMigrationsByBatch($batch): array
    {
        return $this->getMigrations($batch);
    }

    /**
     * Set the information source (not applicable for Neo4j)
     *
     * @param mixed $name Source name
     * @return void
     */
    public function setSource($name): void
    {
        // Not applicable for Neo4j implementation
        // This method exists for compatibility with newer Laravel versions
    }
}