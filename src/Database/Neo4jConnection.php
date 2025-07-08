<?php

namespace SignalNorth\LaravelNeo4j\Database;

use SignalNorth\LaravelNeo4j\Database\Query\Grammars\Neo4jGrammar;
use SignalNorth\LaravelNeo4j\Database\Query\Processors\Neo4jProcessor;
use SignalNorth\LaravelNeo4j\Database\Schema\Grammars\Neo4jGrammar as Neo4jSchemaGrammar;
use SignalNorth\LaravelNeo4j\Database\Schema\Neo4jSchemaBuilder;
use Illuminate\Database\Connection;
use Illuminate\Database\DetectsLostConnections;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\StatementPrepared;
use Illuminate\Database\QueryException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Exception;
use Throwable;

/**
 * Neo4j Database Connection
 *
 * Provides Laravel database connection functionality for Neo4j graph database.
 * Extends Laravel's base Connection class to integrate Neo4j with Laravel's
 * database abstraction layer using Cypher queries.
 *
 * @pattern Adapter Pattern - Adapts Neo4j client to Laravel's connection interface
 * @pattern Template Pattern - Overrides specific methods while maintaining base behavior
 * @package SignalNorth\LaravelNeo4j\Database
 * @since 1.0.0
 * @security Implements secure query execution and transaction management
 */
class Neo4jConnection extends Connection
{
    use DetectsLostConnections;

    /**
     * The Neo4j client instance
     *
     * @var ClientInterface
     */
    protected $neo4jClient;

    /**
     * The active Neo4j session
     *
     * @var SessionInterface|null
     */
    protected $neo4jSession;

    /**
     * The active Neo4j transaction
     *
     * @var TransactionInterface|null
     */
    protected $neo4jTransaction;

    /**
     * Create a new Neo4j connection instance
     *
     * @param ClientInterface $neo4jClient Neo4j client instance
     * @param string $database Database name
     * @param string $tablePrefix Table prefix (not used in Neo4j)
     * @param array $config Connection configuration
     */
    public function __construct(ClientInterface $neo4jClient, string $database = '', string $tablePrefix = '', array $config = [])
    {
        $this->neo4jClient = $neo4jClient;
        $this->database = $database;
        $this->tablePrefix = $tablePrefix;
        $this->config = $config;

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
        $this->useDefaultSchemaGrammar();
    }

    /**
     * Get the Neo4j client instance
     *
     * @return ClientInterface
     */
    public function getNeo4jClient(): ClientInterface
    {
        return $this->neo4jClient;
    }

    /**
     * Get the current Neo4j session
     *
     * @return SessionInterface
     */
    public function getNeo4jSession(): SessionInterface
    {
        if (!$this->neo4jSession) {
            $this->neo4jSession = $this->neo4jClient->createSession();
        }

        return $this->neo4jSession;
    }

    /**
     * Get the driver title for this connection
     *
     * @return string
     */
    public function getDriverTitle(): string
    {
        return 'Neo4j';
    }

    /**
     * Get the default query grammar instance
     *
     * @return Neo4jGrammar
     */
    protected function getDefaultQueryGrammar(): Neo4jGrammar
    {
        return $this->withTablePrefix(new Neo4jGrammar());
    }

    /**
     * Get the default schema grammar instance
     *
     * @return Neo4jSchemaGrammar
     */
    protected function getDefaultSchemaGrammar(): Neo4jSchemaGrammar
    {
        return $this->withTablePrefix(new Neo4jSchemaGrammar());
    }

    /**
     * Get the default post processor instance
     *
     * @return Neo4jProcessor
     */
    protected function getDefaultPostProcessor(): Neo4jProcessor
    {
        return new Neo4jProcessor();
    }

    /**
     * Get a schema builder instance for the connection
     *
     * @return Neo4jSchemaBuilder
     */
    public function getSchemaBuilder(): Neo4jSchemaBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new Neo4jSchemaBuilder($this);
    }

    /**
     * Run a select statement against the database
     *
     * @param string $query Cypher query
     * @param array $bindings Query parameters
     * @param bool $useReadPdo Ignored for Neo4j
     * @return array Query results
     */
    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return [];
            }

            $session = $this->getNeo4jSession();
            $result = $this->neo4jTransaction 
                ? $this->neo4jTransaction->run($query, $bindings)
                : $session->run($query, $bindings);

            return $this->transformResults($result);
        });
    }

    /**
     * Run an insert statement against the database
     *
     * @param string $query Cypher query
     * @param array $bindings Query parameters
     * @return bool Success status
     */
    public function insert($query, $bindings = []): bool
    {
        return $this->statement($query, $bindings);
    }

    /**
     * Run an update statement against the database
     *
     * @param string $query Cypher query
     * @param array $bindings Query parameters
     * @return int Number of affected records
     */
    public function update($query, $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Run a delete statement against the database
     *
     * @param string $query Cypher query
     * @param array $bindings Query parameters
     * @return int Number of affected records
     */
    public function delete($query, $bindings = []): int
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute a Cypher statement and return the boolean result
     *
     * @param string $query Cypher query
     * @param array $bindings Query parameters
     * @return bool Success status
     */
    public function statement($query, $bindings = []): bool
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return true;
            }

            $session = $this->getNeo4jSession();
            $result = $this->neo4jTransaction 
                ? $this->neo4jTransaction->run($query, $bindings)
                : $session->run($query, $bindings);

            $this->recordsHaveBeenModified();

            return true;
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected
     *
     * @param string $query Cypher query
     * @param array $bindings Query parameters
     * @return int Number of affected rows
     */
    public function affectingStatement($query, $bindings = []): int
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            if ($this->pretending()) {
                return 0;
            }

            $session = $this->getNeo4jSession();
            $result = $this->neo4jTransaction 
                ? $this->neo4jTransaction->run($query, $bindings)
                : $session->run($query, $bindings);

            $this->recordsHaveBeenModified();

            // Get statistics from result summary
            $summary = $result->summarize();
            $counters = $summary->counters();

            return $counters->nodesCreated() + 
                   $counters->nodesDeleted() + 
                   $counters->relationshipsCreated() + 
                   $counters->relationshipsDeleted() +
                   $counters->propertiesSet();
        });
    }

    /**
     * Start a new database transaction
     *
     * @return void
     * @throws Exception
     */
    public function beginTransaction(): void
    {
        $this->createTransaction();
        $this->transactions++;
        $this->fireConnectionEvent('beganTransaction');
    }

    /**
     * Commit the active database transaction
     *
     * @return void
     * @throws Exception
     */
    public function commit(): void
    {
        if ($this->transactions == 1) {
            $this->fireConnectionEvent('committing');
            $this->neo4jTransaction?->commit();
            $this->neo4jTransaction = null;
        }

        $this->transactions = max(0, $this->transactions - 1);
        $this->fireConnectionEvent('committed');
    }

    /**
     * Rollback the active database transaction
     *
     * @param int|null $toLevel Transaction level to rollback to
     * @return void
     * @throws Exception
     */
    public function rollBack($toLevel = null): void
    {
        $toLevel = is_null($toLevel) ? $this->transactions - 1 : $toLevel;

        if ($toLevel < 0 || $toLevel >= $this->transactions) {
            return;
        }

        if ($this->transactions == 1) {
            $this->fireConnectionEvent('rollingBack');
            $this->neo4jTransaction?->rollback();
            $this->neo4jTransaction = null;
        }

        $this->transactions = $toLevel;
        $this->fireConnectionEvent('rolledBack');
    }

    /**
     * Get the number of active transactions
     *
     * @return int
     */
    public function transactionLevel(): int
    {
        return $this->transactions;
    }

    /**
     * Create a new transaction
     *
     * @return void
     */
    protected function createTransaction(): void
    {
        if ($this->transactions == 0) {
            $this->reconnectIfMissingConnection();
            $session = $this->getNeo4jSession();
            $this->neo4jTransaction = $session->beginTransaction();
        }
        // Neo4j doesn't support nested transactions like savepoints
    }

    /**
     * Transform Neo4j results to Laravel-compatible format
     *
     * @param \Laudis\Neo4j\Contracts\ResultInterface $result
     * @return array
     */
    protected function transformResults($result): array
    {
        $records = [];
        
        foreach ($result as $record) {
            $recordData = [];
            
            foreach ($record as $key => $value) {
                $recordData[$key] = $this->convertNeo4jValue($value);
            }
            
            $records[] = $recordData;
        }
        
        return $records;
    }

    /**
     * Convert Neo4j values to PHP values
     *
     * @param mixed $value
     * @return mixed
     */
    protected function convertNeo4jValue($value)
    {
        if ($value instanceof CypherMap) {
            return $value->toArray();
        }
        
        if ($value instanceof CypherList) {
            return $value->toArray();
        }
        
        return $value;
    }

    /**
     * Reconnect to the database if missing connection
     *
     * @return void
     */
    public function reconnectIfMissingConnection(): void
    {
        if (is_null($this->neo4jClient)) {
            $this->reconnect();
        }
    }

    /**
     * Reconnect to the database
     *
     * @return void
     * @throws Exception
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->neo4jClient = $this->connector->connect($this->config);
        $this->neo4jSession = null;
        $this->neo4jTransaction = null;
    }

    /**
     * Disconnect from the underlying Neo4j connection
     *
     * @return void
     */
    public function disconnect(): void
    {
        $this->neo4jTransaction = null;
        $this->neo4jSession = null;
        $this->neo4jClient = null;
    }

    /**
     * Handle a query exception
     *
     * @param QueryException $e
     * @param string $query
     * @param array $bindings
     * @param \Closure $callback
     * @return void
     * @throws QueryException
     */
    protected function handleQueryException(QueryException $e, $query, $bindings, \Closure $callback): void
    {
        if ($this->causedByLostConnection($e->getPrevious())) {
            $this->reconnect();
        }

        throw $e;
    }

    /**
     * Determine if the given exception was caused by a lost connection
     *
     * @param \Throwable $e
     * @return bool
     */
    protected function causedByLostConnection(\Throwable $e): bool
    {
        return $e instanceof Neo4jException && 
               str_contains($e->getMessage(), 'Connection lost');
    }

    /**
     * Get the current PDO connection (not applicable for Neo4j)
     *
     * @return null
     */
    public function getPdo()
    {
        return null;
    }

    /**
     * Get the current PDO connection used for reading (not applicable for Neo4j)
     *
     * @return null
     */
    public function getReadPdo()
    {
        return null;
    }

    /**
     * Set the PDO connection (not applicable for Neo4j)
     *
     * @param mixed $pdo
     * @return $this
     */
    public function setPdo($pdo)
    {
        return $this;
    }

    /**
     * Set the PDO connection used for reading (not applicable for Neo4j)
     *
     * @param mixed $pdo
     * @return $this
     */
    public function setReadPdo($pdo)
    {
        return $this;
    }
}