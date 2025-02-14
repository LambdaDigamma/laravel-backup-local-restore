<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Commands;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Prompts\Prompt;
use Wnx\LaravelBackupRestore\Actions\CheckDependenciesAction;
use Wnx\LaravelBackupRestore\Actions\CleanupLocalBackupAction;
use Wnx\LaravelBackupRestore\Actions\DecompressBackupAction;
use Wnx\LaravelBackupRestore\Actions\DownloadBackupAction;
use Wnx\LaravelBackupRestore\Actions\ImportDumpAction;
use Wnx\LaravelBackupRestore\Actions\ResetDatabaseAction;
use Wnx\LaravelBackupRestore\Exceptions\CannotCreateDbImporter;
use Wnx\LaravelBackupRestore\Exceptions\CliNotFound;
use Wnx\LaravelBackupRestore\Exceptions\DecompressionFailed;
use Wnx\LaravelBackupRestore\Exceptions\ImportFailed;
use Wnx\LaravelBackupRestore\Exceptions\NoBackupsFound;
use Wnx\LaravelBackupRestore\Exceptions\NoDatabaseDumpsFound;
use Wnx\LaravelBackupRestore\HealthChecks\HealthCheck;
use Wnx\LaravelBackupRestore\HealthChecks\Result;
use Wnx\LaravelBackupRestore\PendingDatabaseRestore;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class RestoreDatabaseCommand extends BaseRestoreCommand
{
    public $signature = 'backup:restore
                        {--disk= : The disk from where to restore the backup from. Defaults to the first disk in config/backup.php.}
                        {--backup= : The backup to restore. Defaults to the latest backup.}
                        {--connection= : The database connection to restore the backup to. Defaults to the first connection in config/backup.php.}
                        {--password= : The password to decrypt the backup.}
                        {--reset : Drop all tables in the database before restoring the backup.}';

    public $description = 'Restore a database backup dump from a given disk to a database connection.';

    /**
     * @throws NoDatabaseDumpsFound
     * @throws NoBackupsFound
     * @throws CannotCreateDbImporter
     * @throws DecompressionFailed
     * @throws ImportFailed
     * @throws CliNotFound
     */
    public function handle(
        CheckDependenciesAction $checkDependenciesAction,
        DownloadBackupAction $downloadBackupAction,
        DecompressBackupAction $decompressBackupAction,
        ResetDatabaseAction $resetDatabaseAction,
        ImportDumpAction $importDumpAction,
        CleanupLocalBackupAction $cleanupLocalBackupAction
    ): int {
        Prompt::fallbackWhen(
            ! $this->input->isInteractive() || windows_os() || app()->runningUnitTests()
        );

        $connection = $this->option('connection') ?? config('backup.backup.source.databases')[0];

        $diskToRestoreFrom = $this->getDestinationDiskToRestoreFrom();

        $pendingRestore = PendingDatabaseRestore::make(
            disk: $diskToRestoreFrom,
            backup: $this->getBackupToRestore($diskToRestoreFrom),
            connection: $connection,
            backupPassword: $this->getPassword(),
        );

        if (! $this->confirmRestoreProcess($pendingRestore)) {
            warning('Abort.');

            return self::INVALID;
        }

        $downloadBackupAction->execute($pendingRestore);
        $decompressBackupAction->execute($pendingRestore);

        if ($this->option('reset')) {
            $resetDatabaseAction->execute($pendingRestore);
        }

        $importDumpAction->execute($pendingRestore);

        info('Cleaning up …');
        $cleanupLocalBackupAction->execute($pendingRestore);

        return $this->runHealthChecks($pendingRestore);
    }

    protected function getDestinationDiskToRestoreFrom(): string
    {
        if ($this->option('disk')) {
            return $this->option('disk');
        }

        $availableDestinations = config('backup.backup.destination.disks');

        if (count($availableDestinations) === 1) {
            return $availableDestinations[0];
        }

        return select(
            'From which disk should the backup be restored?',
            $availableDestinations,
            head($availableDestinations)
        );
    }

    protected function getBackupToRestore(string $disk): string
    {
        $name = config('backup.backup.name');

        info("Fetch list of backups from $disk …");
        $listOfBackups = collect(Storage::disk($disk)->allFiles($name))
            ->filter(fn ($file) => Str::endsWith($file, '.zip'));

        if ($listOfBackups->count() === 0) {
            error("No backups found on {$disk}.");
            throw NoBackupsFound::onDisk($disk);
        }

        if ($this->option('backup') === 'latest') {
            return $listOfBackups->last();
        }

        if ($this->option('backup')) {
            return $this->option('backup');
        }

        return select(
            label: 'Which backup should be restored?',
            options: $listOfBackups->mapWithKeys(fn ($backup) => [
                $backup => $backup,
            ]),
            default: $listOfBackups->last(),
            scroll: 10
        );
    }

    protected function getPassword(): ?string
    {
        if ($this->option('password')) {
            return $this->option('password');
        }

        if ($this->option('no-interaction')) {
            return config('backup.backup.password');
        }

        return confirm('Use encryption password from config?', true)
            ? config('backup.backup.password')
            : password('What is the password to decrypt the backup? (leave empty if not encrypted)');
    }

    private function runHealthChecks(PendingDatabaseRestore $pendingRestore): int
    {
        $failedResults = collect(config('backup-restore.health-checks'))
            ->map(fn ($check) => $check::new())
            ->map(fn (HealthCheck $check) => $check->run($pendingRestore))
            ->filter(fn (Result $result) => $result->status === self::FAILURE);

        if ($failedResults->count() > 0) {
            $failedResults->each(fn (Result $result) => error($result->message));

            return self::FAILURE;
        }

        info('All health checks passed.');

        return self::SUCCESS;
    }

    public function confirmRestoreProcess(PendingDatabaseRestore $pendingRestore): bool
    {

        return confirm(
            label: sprintf(
                'Proceed to restore disk "%s" using the "%s" disk from backup.',
                'media',
                'media'
            ),
            default: true
        );
    }
}
