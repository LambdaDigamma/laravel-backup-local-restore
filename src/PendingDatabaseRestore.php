<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SensitiveParameter;

class PendingDatabaseRestore extends PendingRestore
{
    public function __construct(
        string $disk,
        string $backup,
        public readonly string $connection,
        string $restoreId,
        string $restoreName,
        #[SensitiveParameter] ?string $backupPassword = null,
        string $restoreDisk = 'local',
    ) {
        parent::__construct(
            disk: $disk,
            backup: $backup,
            restoreId: $restoreId,
            restoreName: $restoreName,
            backupPassword: $backupPassword,
            restoreDisk: $restoreDisk,
        );
    }

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

    /** @deprecated  */
    public function hasNoDbDumpsDirectory(): bool
    {
        return ! Storage::disk($this->restoreDisk)
            ->has($this->getPathToLocalDecompressedBackup().DIRECTORY_SEPARATOR.'db-dumps');
    }

    public function getAvailableFilesInDbDumpsDirectory(): Collection
    {
        $files = Storage::disk($this->restoreDisk)
            ->files($this->getPathToLocalDecompressedBackup().DIRECTORY_SEPARATOR.'db-dumps');

        return collect($files);
    }

    public function getAvailableDbDumps(): Collection
    {
        return $this->getAvailableFilesInDbDumpsDirectory()
            ->filter(fn ($file) => Str::endsWith($file, ['.sql', '.sql.gz', '.sql.bz2']));
    }
}
