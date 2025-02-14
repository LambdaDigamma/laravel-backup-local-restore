<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Events;

use Wnx\LaravelBackupRestore\PendingDatabaseRestore;

class DatabaseRestored
{
    public function __construct(readonly public PendingDatabaseRestore $pendingRestore) {}
}
