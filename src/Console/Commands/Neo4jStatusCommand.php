<?php

namespace SignalNorth\LaravelNeo4j\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SignalNorth\LaravelNeo4j\Facades\Neo4j;
use Exception;

/**
 * Neo4j Status Command
 *
 * Tests the Neo4j connection and displays database information.
 * Useful for debugging connection issues and verifying configuration.
 *
 * @pattern Command Pattern - Encapsulates status checking logic
 * @package SignalNorth\LaravelNeo4j\Console\Commands
 * @since 1.0.0
 */
class Neo4jStatusCommand extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'neo4j:status 
                            {--connection= : The database connection to use}
                            {--detailed : Show detailed database information}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Check the status of Neo4j database connection';

    /**
     * Execute the console command
     *
     * @return int
     */
    public function handle(): int
    {
        $connectionName = $this->option('connection') ?: config('neo4j.default');
        
        $this->info("Checking Neo4j connection: {$connectionName}");
        $this->line('');

        try {
            // Test the connection
            $connection = DB::connection($connectionName);
            
            // Run a simple query to verify connection
            $result = $connection->select('RETURN 1 as connected');
            
            if (!empty($result) && $result[0]['connected'] === 1) {
                $this->info('✓ Connection successful!');
                
                if ($this->option('detailed')) {
                    $this->showDetailedInfo($connection);
                }
            } else {
                $this->error('✗ Connection test failed');
                return Command::FAILURE;
            }
            
        } catch (Exception $e) {
            $this->error('✗ Connection failed!');
            $this->error('Error: ' . $e->getMessage());
            
            $this->line('');
            $this->line('Please check your configuration:');
            $this->table(
                ['Setting', 'Value'],
                [
                    ['Host', config("database.connections.{$connectionName}.host")],
                    ['Port', config("database.connections.{$connectionName}.port")],
                    ['Scheme', config("database.connections.{$connectionName}.scheme")],
                    ['Database', config("database.connections.{$connectionName}.database")],
                    ['Username', config("database.connections.{$connectionName}.username")],
                ]
            );
            
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Show detailed database information
     *
     * @param \Illuminate\Database\Connection $connection
     * @return void
     */
    protected function showDetailedInfo($connection): void
    {
        $this->line('');
        $this->info('Database Information:');
        
        try {
            // Get database version
            $version = $connection->select('CALL dbms.components() YIELD name, versions WHERE name = "Neo4j Kernel" RETURN versions[0] as version');
            if (!empty($version)) {
                $this->line('Neo4j Version: ' . $version[0]['version']);
            }
            
            // Get database name
            $dbInfo = $connection->select('CALL db.info() YIELD name RETURN name');
            if (!empty($dbInfo)) {
                $this->line('Database Name: ' . $dbInfo[0]['name']);
            }
            
            // Count nodes
            $nodeCount = $connection->select('MATCH (n) RETURN count(n) as count');
            $this->line('Total Nodes: ' . number_format($nodeCount[0]['count']));
            
            // Count relationships
            $relCount = $connection->select('MATCH ()-[r]->() RETURN count(r) as count');
            $this->line('Total Relationships: ' . number_format($relCount[0]['count']));
            
            // Get node labels
            $labels = $connection->select('CALL db.labels() YIELD label RETURN label ORDER BY label');
            if (!empty($labels)) {
                $this->line('');
                $this->info('Node Labels:');
                foreach ($labels as $label) {
                    $count = $connection->select("MATCH (n:`{$label['label']}`) RETURN count(n) as count");
                    $this->line("  - {$label['label']}: " . number_format($count[0]['count']));
                }
            }
            
            // Get relationship types
            $types = $connection->select('CALL db.relationshipTypes() YIELD relationshipType RETURN relationshipType ORDER BY relationshipType');
            if (!empty($types)) {
                $this->line('');
                $this->info('Relationship Types:');
                foreach ($types as $type) {
                    $count = $connection->select("MATCH ()-[r:`{$type['relationshipType']}`]->() RETURN count(r) as count");
                    $this->line("  - {$type['relationshipType']}: " . number_format($count[0]['count']));
                }
            }
            
        } catch (Exception $e) {
            $this->warn('Could not retrieve detailed information: ' . $e->getMessage());
        }
    }
}