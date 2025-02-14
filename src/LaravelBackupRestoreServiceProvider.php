<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Wnx\LaravelBackupRestore\Commands\RestoreDatabaseCommand;
use Wnx\LaravelBackupRestore\Commands\RestoreStorageCommand;

class LaravelBackupRestoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-backup-restore')
            ->hasConfigFile('backup-restore')
            ->hasCommand(RestoreDatabaseCommand::class)
            ->hasCommand(RestoreStorageCommand::class);
    }
}
