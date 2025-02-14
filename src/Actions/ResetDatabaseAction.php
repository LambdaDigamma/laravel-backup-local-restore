<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\DB;
use Wnx\LaravelBackupRestore\Events\DatabaseReset;
use Wnx\LaravelBackupRestore\PendingDatabaseRestore;

use function Laravel\Prompts\info;

class ResetDatabaseAction
{
    public function execute(PendingDatabaseRestore $pendingRestore)
    {
        info('Reset database …');

        DB::connection($pendingRestore->connection)
            ->getSchemaBuilder()
            ->dropAllTables();

        event(new DatabaseReset($pendingRestore));
    }
}
