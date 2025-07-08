<?php

namespace SignalNorth\LaravelNeo4j\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Neo4j Migrate Command
 *
 * Wrapper command for running Neo4j-specific migrations.
 * Ensures migrations run against the correct Neo4j connection.
 *
 * @pattern Command Pattern - Encapsulates migration execution
 * @pattern Proxy Pattern - Proxies to Laravel's migrate command
 * @package SignalNorth\LaravelNeo4j\Console\Commands
 * @since 1.0.0
 */
class Neo4jMigrateCommand extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'neo4j:migrate 
                            {--force : Force the operation to run in production}
                            {--path= : The path to the migrations}
                            {--pretend : Dump the queries that would be run}
                            {--seed : Run database seeders}
                            {--step : Force migrations to run one at a time}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Run Neo4j database migrations';

    /**
     * Execute the console command
     *
     * @return int
     */
    public function handle(): int
    {
        $connection = config('neo4j.default', 'neo4j');
        $path = $this->option('path') ?: config('neo4j.migrations.path', 'database/neo4j-migrations');
        
        $this->info('Running Neo4j migrations...');
        
        // Build the options array for the migrate command
        $options = [
            '--database' => $connection,
            '--path' => $path,
        ];
        
        if ($this->option('force')) {
            $options['--force'] = true;
        }
        
        if ($this->option('pretend')) {
            $options['--pretend'] = true;
        }
        
        if ($this->option('step')) {
            $options['--step'] = true;
        }
        
        // Run the standard migrate command with Neo4j connection
        $result = $this->call('migrate', $options);
        
        if ($this->option('seed') && $result === 0) {
            $this->line('');
            $this->call('db:seed', ['--database' => $connection]);
        }
        
        return $result;
    }
}