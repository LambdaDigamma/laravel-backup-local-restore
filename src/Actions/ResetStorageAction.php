<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Wnx\LaravelBackupRestore\Storage\PendingStorageRestore;

use function Laravel\Prompts\info;

class ResetStorageAction
{
    public function execute(PendingStorageRestore $pendingRestore)
    {
        info('Reset directory …');

        //        DB::connection($pendingRestore->connection)
        //            ->getSchemaBuilder()
        //            ->dropAllTables();
        //
        //        event(new DatabaseReset($pendingRestore));
    }
}
