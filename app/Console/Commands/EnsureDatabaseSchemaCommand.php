<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnsureDatabaseSchemaCommand extends Command
{
    protected $signature = 'db:ensure-schema';

    protected $description = 'Create the schema the app keeps its tables in (DB_SCHEMA), if it is missing; the deploy runs it before migrations';

    public function handle(): int
    {
        $schema = (string) config('database.connections.pgsql.search_path');

        if ($schema === 'public') {
            $this->info('Tables live in public; nothing to create.');

            return self::SUCCESS;
        }

        // Owned by the app's role, so other roles of the server cannot create tables in it
        DB::statement('create schema if not exists '.DB::getQueryGrammar()->wrap($schema));
        $this->info("Schema {$schema} is ready.");

        return self::SUCCESS;
    }
}
