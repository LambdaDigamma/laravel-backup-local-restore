<?php

namespace Wnx\LaravelBackupRestore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Wnx\LaravelBackupRestore\Exceptions\NoBackupsFound;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;

class BaseRestoreCommand extends Command
{
    protected function getDestinationDiskToRestoreFrom(): string
    {
        // Use disk from --disk option if provided
        if ($this->option('disk')) {
            return $this->option('disk');
        }

        $availableDestinations = config('backup.backup.destination.disks');

        // If there is only one disk configured, use it
        if (count($availableDestinations) === 1) {
            return $availableDestinations[0];
        }

        // Ask user to choose a disk
        return select(
            'From which disk should the backup be restored?',
            $availableDestinations,
            head($availableDestinations)
        );
    }

    /**
     * @throws NoBackupsFound
     */
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
            options: $listOfBackups->mapWithKeys(fn ($backup) => [$backup => $backup]),
            default: $listOfBackups->last(),
            scroll: 10
        );
    }

    protected function getPassword(): ?string
    {
        if ($this->option('password')) {
            $password = $this->option('password');
        } elseif ($this->option('no-interaction')) {
            $password = config('backup.backup.password');
        } elseif (confirm('Use encryption password from config?', true)) {
            $password = config('backup.backup.password');
        } else {
            $password = password('What is the password to decrypt the backup? (leave empty if not encrypted)');
        }

        return $password;
    }
}
