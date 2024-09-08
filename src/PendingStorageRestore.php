<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore;

use Illuminate\Support\Str;

class PendingStorageRestore extends PendingRestore
{
    public static function make(...$attributes): PendingStorageRestore
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
