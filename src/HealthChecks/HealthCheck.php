<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\HealthChecks;

use Wnx\LaravelBackupRestore\PendingDatabaseRestore;

abstract class HealthCheck
{
    abstract public function run(PendingDatabaseRestore $pendingRestore): Result;

    public static function new(): self
    {
        return app(static::class);
    }
}
