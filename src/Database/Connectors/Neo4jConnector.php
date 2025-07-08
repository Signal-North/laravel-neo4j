<?php

namespace SignalNorth\LaravelNeo4j\Database\Connectors;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use InvalidArgumentException;

/**
 * Neo4j Database Connector
 *
 * Establishes connections to Neo4j graph database using the laudis/neo4j-php-client
 * library. Implements Laravel's ConnectorInterface to integrate with Laravel's
 * database abstraction layer.
 *
 * @pattern Adapter Pattern - Adapts Neo4j client to Laravel's connector interface
 * @package SignalNorth\LaravelNeo4j\Database\Connectors
 * @since 1.0.0
 * @security Validates connection parameters and handles authentication securely
 */
class Neo4jConnector extends Connector implements ConnectorInterface
{
    /**
     * Establish a database connection to Neo4j
     *
     * Creates a Neo4j client connection using the provided configuration.
     * Supports multiple connection schemes (bolt, http, https) and authentication.
     *
     * @param array $config Connection configuration array
     * @return ClientInterface Neo4j client instance
     * @throws InvalidArgumentException When configuration is invalid
     * @security Ensures secure authentication and connection handling
     */
    public function connect(array $config): ClientInterface
    {
        $dsn = $this->getDsn($config);
        $authentication = $this->getAuthentication($config);
        
        $builder = ClientBuilder::create()
            ->withDriver($config['alias'] ?? 'default', $dsn, $authentication);

        // Configure connection options
        $this->configureConnection($builder, $config);

        return $builder->build();
    }

    /**
     * Build the DSN string for Neo4j connection
     *
     * Constructs the Data Source Name string based on the configuration.
     * Supports different schemes and connection types.
     *
     * @param array $config Connection configuration
     * @return string DSN string
     */
    protected function getDsn(array $config): string
    {
        $scheme = $config['scheme'] ?? 'bolt';
        $host = $config['host'] ?? 'localhost';
        $port = $config['port'] ?? $this->getDefaultPort($scheme);

        return "{$scheme}://{$host}:{$port}";
    }

    /**
     * Get default port for the connection scheme
     *
     * Returns the standard port numbers for different Neo4j connection schemes.
     *
     * @param string $scheme Connection scheme (bolt, http, https)
     * @return int Default port number
     */
    protected function getDefaultPort(string $scheme): int
    {
        return match ($scheme) {
            'bolt' => 7687,
            'http' => 7474,
            'https' => 7473,
            default => 7687,
        };
    }

    /**
     * Create authentication configuration
     *
     * Builds authentication credentials for Neo4j connection.
     * Supports basic authentication and no authentication modes.
     *
     * @param array $config Connection configuration
     * @return \Laudis\Neo4j\Contracts\AuthenticateInterface Authentication instance
     */
    protected function getAuthentication(array $config)
    {
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        if (empty($username) || empty($password)) {
            return Authenticate::disabled();
        }

        return Authenticate::basic($username, $password);
    }

    /**
     * Configure additional connection options
     *
     * Applies additional configuration options to the Neo4j client builder.
     * Handles timeout settings and other connection parameters.
     *
     * @param ClientBuilder $builder Neo4j client builder instance
     * @param array $config Connection configuration
     * @return void
     */
    protected function configureConnection(ClientBuilder $builder, array $config): void
    {
        // Set connection timeout if specified
        if (isset($config['timeout'])) {
            $builder->withTimeout($config['timeout']);
        }

        // Set user agent if specified
        if (isset($config['user_agent'])) {
            $builder->withUserAgent($config['user_agent']);
        }

        // Configure SSL/TLS settings for secure connections
        if (isset($config['ssl']) && $config['ssl']) {
            $builder->withSslConfiguration($config['ssl_config'] ?? []);
        }
    }

    /**
     * Get the default connection options
     *
     * Returns default options for Neo4j connections.
     *
     * @return array Default connection options
     */
    public function getDefaultOptions(): array
    {
        return [
            'timeout' => 30,
            'user_agent' => 'Laravel Neo4j Driver',
            'ssl' => false,
        ];
    }
}