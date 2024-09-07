<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Storage;

use Illuminate\Support\Str;
use Wnx\LaravelBackupRestore\PendingDatabaseRestore;
use Wnx\LaravelBackupRestore\PendingRestore;

class PendingStorageRestore extends PendingRestore
{
    public static function make(...$attributes): PendingDatabaseRestore
    {
        $restoreName = now()->format('Y-m-d-h-i-s').'-'.Str::uuid();

        /** @phpstan-ignore-next-line */
        return new self(
            ...$attributes,
            restoreName: $restoreName,
            restoreId: $restoreName,
        );
    }
}
