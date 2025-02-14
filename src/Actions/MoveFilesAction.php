<?php

declare(strict_types=1);

namespace Wnx\LaravelBackupRestore\Actions;

use Illuminate\Support\Facades\Storage;
use Wnx\LaravelBackupRestore\PendingStorageRestore;

use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

class MoveFilesAction
{
    public function execute(PendingStorageRestore $pendingRestore, string $backupPath, string $pathToCopy): void
    {

        // Original path that might need to be dynamically adjusted
        $path = $backupPath;
        $root = str($path)->trim()->ltrim('/')->toString(); // Trim spaces and leading slashes

        // Get the decompressed backup path
        $appBasePath = $pendingRestore->getPathToLocalDecompressedBackup().DIRECTORY_SEPARATOR.$root;
        info("Base path: $appBasePath");

        // Define the backup storage path and disk storage path
        $storagePathBackup = $appBasePath.DIRECTORY_SEPARATOR.'storage';
        $storagePathDisk = storage_path(); // Laravel's storage path

        // Check if the source directory exists
        $sourceDirectory = $storagePathBackup.DIRECTORY_SEPARATOR.$pathToCopy;
        if (! Storage::exists($sourceDirectory)) {
            info("The directory $sourceDirectory does not exist. Aborting operation.");

            return;
        }

        // List and count files and directories in the backup directory
        $filesInBackup = Storage::files($sourceDirectory);
        $directoriesInBackup = Storage::directories($sourceDirectory);

        info('Found '.count($filesInBackup)." files in $pathToCopy of the backup");

        $this->printPreviewTable($filesInBackup, $storagePathBackup);

        info('---------------------------------');

        info('Found '.count($directoriesInBackup)." directories in $pathToCopy of the backup");

        $this->printPreviewTable($directoriesInBackup, $storagePathBackup);

        // Destination path
        $destinationDirectory = str($storagePathDisk.DIRECTORY_SEPARATOR.$pathToCopy)->replace(storage_path().DIRECTORY_SEPARATOR, '')->toString();

        info("Copying files from $sourceDirectory to $destinationDirectory");

        $allFiles = Storage::allFiles($sourceDirectory);

        foreach ($allFiles as $file) {

            $destination = str($file)->replace($storagePathBackup.DIRECTORY_SEPARATOR.'app', '')->ltrim('/')->toString();

            if (Storage::disk('local')->copy($file, $destination)) {
                info("Copied $file to $destination");
            } else {
                info("Failed to copy $file to $destination");
            }

        }

        // List contents of path after copying
        $filesInDisk = Storage::files($pathToCopy);
        $directoriesInDisk = Storage::directories($pathToCopy);

        Storage::put('test.txt', 'Hello World');

        info('---------------------------------');
        $this->printPreviewTable($filesInDisk, $storagePathDisk);
        info('---------------------------------');
        $this->printPreviewTable($directoriesInDisk, $storagePathDisk);

    }

    private function prettifyPath(string $path, string $basePath): string
    {
        return str($path)->replace($basePath.DIRECTORY_SEPARATOR, '')->toString();
    }

    private function printPreviewTable(array $files, string $basePath): void
    {
        $limit = 10;
        $rows = collect($files)
            ->take($limit)
            ->map(fn ($file) => ['name' => $this->prettifyPath($file, $basePath)]);

        if (count($files) > $limit) {
            $rows->push(['name' => '...']);
        }

        table(
            headers: ['Name'],
            rows: $rows->toArray(),
        );
    }
}
