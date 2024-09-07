<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Events;

use Wnx\LaravelBackupRestore\PendingDatabaseRestore;

class DatabaseReset
{
    public function __construct(readonly public PendingDatabaseRestore $pendingRestore) {}
}
