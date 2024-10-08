<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\HealthChecks\Checks;

use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\HealthChecks\HealthCheck;
use Wnx\LaravelBackupRestore\HealthChecks\Result;
use Wnx\LaravelBackupRestore\PendingDatabaseRestore;

class DatabaseHasTables extends HealthCheck
{
    public function run(PendingDatabaseRestore $pendingRestore): Result
    {
        $result = Result::make($this);

        $schemaBuilder = DB::connection($pendingRestore->connection)
            ->getSchemaBuilder();

        if (method_exists($schemaBuilder, 'getTables')) {
            $tables = $schemaBuilder->getTables();
        } else {
            // `getAllTables()` has been removed in Laravel 11.
            /** @phpstan-ignore-next-line  */
            $tables = $schemaBuilder->getAllTables();
        }

        if (count($tables) === 0) {
            return $result->failed('Database has not tables after restore.');
        }

        return $result->ok();
    }
}
