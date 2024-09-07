<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Commands;

use Laravel\Prompts\Prompt;
use Wnx\LaravelBackupRestore\Actions\CleanupLocalBackupAction;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\Actions\ImportDumpAction;
use Wnx\LaravelBackupRestore\Actions\ResetStorageAction;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;
use Wnx\LaravelBackupRestore\Exceptions\CliNotFound;
use Wnx\LaravelBackupRestore\Exceptions\DecompressionFailed;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;
use Wnx\LaravelBackupRestore\Exceptions\NoBackupsFound;
use Wnx\LaravelBackupRestore\Exceptions\NoDatabaseDumpsFound;
use Wnx\LaravelBackupRestore\Storage\PendingStorageRestore;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class RestoreStorageCommand extends BaseRestoreCommand
{
    public $signature = 'backup:restore-disk
                        {--disk= : The disk from where to restore the backup from. Defaults to the first disk in config/backup.php.}
                        {--backup= : The backup to restore. Defaults to the latest backup.}
                        {--password= : The password to decrypt the backup.}
                        {--reset : Drop all tables in the database before restoring the backup.}';

    public $description = 'Restore a local disk content from a given disk.';

    /**
     * @throws NoDatabaseDumpsFound
     * @throws NoBackupsFound
     * @throws CannotCreateDbImporter
     * @throws DecompressionFailed
     * @throws ImportFailed
     * @throws CliNotFound
     */
    public function handle(
        DownloadBackupAction $downloadBackupAction,
        DecompressBackupAction $decompressBackupAction,
        ResetStorageAction $resetStorageAction,
        ImportDumpAction $importDumpAction,
        CleanupLocalBackupAction $cleanupLocalBackupAction
    ): int {
        Prompt::fallbackWhen(
            ! $this->input->isInteractive() || windows_os() || app()->runningUnitTests()
        );

        $diskToRestoreFrom = $this->getDestinationDiskToRestoreFrom();

        $pendingRestore = PendingStorageRestore::make(
            disk: $diskToRestoreFrom,
            backup: $this->getBackupToRestore($diskToRestoreFrom),
            backupPassword: $this->getPassword(),
        );

        if (! $this->confirmRestoreProcess($pendingRestore)) {
            warning('Abort.');

            return self::INVALID;
        }

        $downloadBackupAction->execute($pendingRestore);
        $decompressBackupAction->execute($pendingRestore);

        if ($this->option('reset')) {
            $resetStorageAction->execute($pendingRestore);
        }
        //
        //        $importDumpAction->execute($pendingRestore);
        //

        info('Cleaning up …');
        $cleanupLocalBackupAction->execute($pendingRestore);

        return 0;
        //        return $this->runHealthChecks($pendingRestore);
    }

    private function confirmRestoreProcess(PendingStorageRestore $pendingRestore): bool
    {

        return confirm(
            label: sprintf(
                'Proceed to restore disk "%s" using the "%s" disk from backup.',
                'media',
                'media'
            ),
            default: true
        );

        //        $connectionConfig = config("database.connections.{$pendingRestore->connection}");
        //        $connectionInformationForConfirmation = collect([
        //            'Database' => Arr::get($connectionConfig, 'database'),
        //            'Host' => Arr::get($connectionConfig, 'host'),
        //            'username' => Arr::get($connectionConfig, 'username'),
        //        ])->filter()->map(fn ($value, $key) => "{$key}: {$value}")->implode(', ');
        //
        //        return confirm(
        //            label: sprintf(
        //                'Proceed to restore "%s" using the "%s" database connection. (%s)',
        //                $pendingRestore->backup,
        //                $pendingRestore->connection,
        //                $connectionInformationForConfirmation
        //            ),
        //            default: true
        //        );
    }
}
