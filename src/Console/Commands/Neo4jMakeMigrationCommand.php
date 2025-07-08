<?php

namespace SignalNorth\LaravelNeo4j\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

/**
 * Neo4j Make Migration Command
 *
 * Creates new Neo4j migration files with appropriate boilerplate code.
 * Generates timestamped migration files in the Neo4j migrations directory.
 *
 * @pattern Command Pattern - Encapsulates migration creation logic
 * @pattern Template Pattern - Provides migration file template
 * @package SignalNorth\LaravelNeo4j\Console\Commands
 * @since 1.0.0
 */
class Neo4jMakeMigrationCommand extends Command
{
    /**
     * The name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'neo4j:make:migration 
                            {name : The name of the migration}
                            {--create= : The node label to be created}
                            {--constraint= : Create a constraint migration}
                            {--index= : Create an index migration}';

    /**
     * The console command description
     *
     * @var string
     */
    protected $description = 'Create a new Neo4j migration file';

    /**
     * Execute the console command
     *
     * @return int
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $className = Str::studly($name);
        $fileName = $this->getFileName($name);
        $path = $this->getMigrationPath($fileName);

        if (File::exists($path)) {
            $this->error("Migration {$fileName} already exists!");
            return Command::FAILURE;
        }

        // Create migration directory if it doesn't exist
        $directory = dirname($path);
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Generate migration content
        $stub = $this->getMigrationStub();
        $content = $this->populateStub($stub, $className);

        // Write the migration file
        File::put($path, $content);

        $this->info("Created Migration: {$fileName}");
        $this->line("Migration path: {$path}");

        return Command::SUCCESS;
    }

    /**
     * Get the migration file name
     *
     * @param string $name
     * @return string
     */
    protected function getFileName(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        $name = Str::snake($name);
        
        return "{$timestamp}_{$name}.php";
    }

    /**
     * Get the full path to the migration
     *
     * @param string $fileName
     * @return string
     */
    protected function getMigrationPath(string $fileName): string
    {
        $directory = config('neo4j.migrations.path', database_path('neo4j-migrations'));
        return $directory . '/' . $fileName;
    }

    /**
     * Get the migration stub file
     *
     * @return string
     */
    protected function getMigrationStub(): string
    {
        if ($this->option('create')) {
            return $this->getCreateNodeStub();
        }

        if ($this->option('constraint')) {
            return $this->getConstraintStub();
        }

        if ($this->option('index')) {
            return $this->getIndexStub();
        }

        return $this->getBlankStub();
    }

    /**
     * Get the blank migration stub
     *
     * @return string
     */
    protected function getBlankStub(): string
    {
        return <<<'STUB'
<?php

use SignalNorth\LaravelNeo4j\Database\Migrations\Neo4jMigration;
use SignalNorth\LaravelNeo4j\Database\Schema\Neo4jBlueprint;
use SignalNorth\LaravelNeo4j\Facades\Neo4j;

class {{ class }} extends Neo4jMigration
{
    /**
     * Run the migrations
     *
     * @return void
     */
    public function up(): void
    {
        // Add your Cypher queries here
        Neo4j::statement('
            // Your CREATE/MERGE statements
        ');
    }

    /**
     * Reverse the migrations
     *
     * @return void
     */
    public function down(): void
    {
        // Add your rollback Cypher queries here
        Neo4j::statement('
            // Your DELETE/DROP statements
        ');
    }
}
STUB;
    }

    /**
     * Get the create node migration stub
     *
     * @return string
     */
    protected function getCreateNodeStub(): string
    {
        $label = $this->option('create');
        
        return <<<STUB
<?php

use SignalNorth\LaravelNeo4j\Database\Migrations\Neo4jMigration;
use SignalNorth\LaravelNeo4j\Database\Schema\Neo4jBlueprint;
use SignalNorth\LaravelNeo4j\Facades\Neo4j;

class {{ class }} extends Neo4jMigration
{
    /**
     * Run the migrations
     *
     * @return void
     */
    public function up(): void
    {
        Neo4j::schema()->createNode('{$label}', function (Neo4jBlueprint \$node) {
            // Define node properties and constraints
            \$node->property('id')->unique();
            \$node->property('name')->index();
            \$node->property('created_at');
            \$node->property('updated_at');
        });
    }

    /**
     * Reverse the migrations
     *
     * @return void
     */
    public function down(): void
    {
        Neo4j::schema()->dropNode('{$label}');
    }
}
STUB;
    }

    /**
     * Get the constraint migration stub
     *
     * @return string
     */
    protected function getConstraintStub(): string
    {
        $label = $this->option('constraint');
        
        return <<<STUB
<?php

use SignalNorth\LaravelNeo4j\Database\Migrations\Neo4jMigration;
use SignalNorth\LaravelNeo4j\Facades\Neo4j;

class {{ class }} extends Neo4jMigration
{
    /**
     * Run the migrations
     *
     * @return void
     */
    public function up(): void
    {
        // Create unique constraint
        Neo4j::statement('
            CREATE CONSTRAINT {$label}_unique_id IF NOT EXISTS
            FOR (n:{$label})
            REQUIRE n.id IS UNIQUE
        ');
    }

    /**
     * Reverse the migrations
     *
     * @return void
     */
    public function down(): void
    {
        // Drop constraint
        Neo4j::statement('DROP CONSTRAINT {$label}_unique_id IF EXISTS');
    }
}
STUB;
    }

    /**
     * Get the index migration stub
     *
     * @return string
     */
    protected function getIndexStub(): string
    {
        $label = $this->option('index');
        
        return <<<STUB
<?php

use SignalNorth\LaravelNeo4j\Database\Migrations\Neo4jMigration;
use SignalNorth\LaravelNeo4j\Facades\Neo4j;

class {{ class }} extends Neo4jMigration
{
    /**
     * Run the migrations
     *
     * @return void
     */
    public function up(): void
    {
        // Create index
        Neo4j::statement('
            CREATE INDEX {$label}_name_index IF NOT EXISTS
            FOR (n:{$label})
            ON (n.name)
        ');
    }

    /**
     * Reverse the migrations
     *
     * @return void
     */
    public function down(): void
    {
        // Drop index
        Neo4j::statement('DROP INDEX {$label}_name_index IF EXISTS');
    }
}
STUB;
    }

    /**
     * Populate the stub with actual values
     *
     * @param string $stub
     * @param string $className
     * @return string
     */
    protected function populateStub(string $stub, string $className): string
    {
        return str_replace('{{ class }}', $className, $stub);
    }
}