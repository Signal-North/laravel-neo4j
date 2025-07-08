<?php

namespace SignalNorth\LaravelNeo4j\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Neo4j Install Command
 *
 * Provides initial setup and configuration for the Neo4j Laravel package.
 * Publishes configuration files and creates necessary directories.
 *
 * @pattern Command Pattern - Encapsulates installation logic
 * @package SignalNorth\LaravelNeo4j\Console\Commands
 * @since 1.0.0
 */
class Neo4jInstallCommand extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'neo4j:install 
                            {--force : Overwrite existing configuration}
                            {--with-example : Include example migrations}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Install the Neo4j Laravel package and publish configuration';

    /**
     * Execute the console command
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('Installing Neo4j Laravel Package...');

        // Publish configuration
        $this->publishConfiguration();

        // Create migration directory
        $this->createMigrationDirectory();

        // Add environment variables
        $this->addEnvironmentVariables();

        // Optionally publish example migrations
        if ($this->option('with-example')) {
            $this->publishExampleMigrations();
        }

        $this->info('Neo4j Laravel Package installed successfully!');
        $this->line('');
        $this->line('Next steps:');
        $this->line('1. Configure your Neo4j connection in .env file');
        $this->line('2. Run "php artisan neo4j:status" to test the connection');
        $this->line('3. Create migrations with "php artisan neo4j:make:migration"');
        $this->line('4. Run migrations with "php artisan neo4j:migrate"');

        return Command::SUCCESS;
    }

    /**
     * Publish the configuration file
     *
     * @return void
     */
    protected function publishConfiguration(): void
    {
        $force = $this->option('force') ? '--force' : '';
        
        $this->call('vendor:publish', [
            '--provider' => 'SignalNorth\LaravelNeo4j\Neo4jServiceProvider',
            '--tag' => 'neo4j-config',
            $force,
        ]);
    }

    /**
     * Create the Neo4j migrations directory
     *
     * @return void
     */
    protected function createMigrationDirectory(): void
    {
        $directory = database_path('neo4j-migrations');
        
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
            $this->info("Created directory: {$directory}");
        }
    }

    /**
     * Add Neo4j environment variables to .env.example
     *
     * @return void
     */
    protected function addEnvironmentVariables(): void
    {
        $envExample = base_path('.env.example');
        
        if (!File::exists($envExample)) {
            return;
        }

        $envVariables = <<<'ENV'

# Neo4j Configuration
NEO4J_SCHEME=bolt
NEO4J_HOST=localhost
NEO4J_PORT=7687
NEO4J_USERNAME=neo4j
NEO4J_PASSWORD=
NEO4J_DATABASE=neo4j
ENV;

        $content = File::get($envExample);
        
        if (!str_contains($content, 'NEO4J_SCHEME')) {
            File::append($envExample, $envVariables);
            $this->info('Added Neo4j environment variables to .env.example');
        }
    }

    /**
     * Publish example migrations
     *
     * @return void
     */
    protected function publishExampleMigrations(): void
    {
        $this->call('vendor:publish', [
            '--provider' => 'SignalNorth\LaravelNeo4j\Neo4jServiceProvider',
            '--tag' => 'neo4j-migrations',
        ]);
        
        $this->info('Published example Neo4j migrations');
    }
}