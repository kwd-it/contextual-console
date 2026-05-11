<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'contextual-console:backup-database';

    protected $description = 'Create a consistent SQLite backup (VACUUM INTO), compress it, upload to object storage, and prune old backups.';

    public function handle(): int
    {
        $default = (string) config('database.default');
        $connectionConfig = config("database.connections.{$default}");

        if (($connectionConfig['driver'] ?? null) !== 'sqlite') {
            $this->error("Default database connection \"{$default}\" is not SQLite; backup aborted.");

            return self::FAILURE;
        }

        try {
            $sourcePath = $this->resolveSqliteDatabasePath((string) ($connectionConfig['database'] ?? ''));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            $this->error("SQLite database file is missing or not readable at: {$sourcePath}");

            return self::FAILURE;
        }

        $disk = trim((string) config('contextual_console.backup.disk'));
        if ($disk === '') {
            $this->error('Backup disk is not configured.');

            return self::FAILURE;
        }

        $remoteDir = str_replace('\\', '/', trim((string) config('contextual_console.backup.path'), '/'));
        $retentionDays = (int) config('contextual_console.backup.retention_days');

        $workDir = storage_path('app/tmp/backups');
        File::ensureDirectoryExists($workDir);

        $stamp = now()->format('Y-m-d-His');
        $basename = "contextual-console-{$stamp}";
        $sqliteBackup = $workDir.DIRECTORY_SEPARATOR.$basename.'.sqlite';
        $gzPath = $workDir.DIRECTORY_SEPARATOR.$basename.'.sqlite.gz';

        $vacuumTarget = $this->sqliteVacuumPathLiteral($sqliteBackup);

        try {
            DB::connection($default)->statement("VACUUM INTO {$vacuumTarget}");
        } catch (Throwable $e) {
            $this->error('VACUUM INTO failed: '.$e->getMessage());
            $this->cleanupLocal([$sqliteBackup, $gzPath]);

            return self::FAILURE;
        }

        if (! is_file($sqliteBackup)) {
            $this->error('VACUUM INTO finished but the backup file was not created.');
            $this->cleanupLocal([$sqliteBackup, $gzPath]);

            return self::FAILURE;
        }

        try {
            $this->gzipFile($sqliteBackup, $gzPath);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            $this->cleanupLocal([$sqliteBackup, $gzPath]);

            return self::FAILURE;
        }

        $this->cleanupLocal([$sqliteBackup]);

        $remoteKey = str_replace('\\', '/', ($remoteDir !== '' ? $remoteDir.'/' : '').$basename.'.sqlite.gz');

        $stream = fopen($gzPath, 'rb');
        if ($stream === false) {
            $this->error('Could not open compressed backup for reading.');
            $this->cleanupLocal([$gzPath]);

            return self::FAILURE;
        }

        try {
            $put = Storage::disk($disk)->put($remoteKey, $stream);

            if ($put === false) {
                throw new RuntimeException('Upload to disk "'.$disk.'" failed (put returned false).');
            }
        } catch (Throwable $e) {
            $this->error('Upload failed: '.$e->getMessage());
            $this->cleanupLocal([$gzPath]);

            return self::FAILURE;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $this->cleanupLocal([$gzPath]);

        if ($retentionDays > 0) {
            try {
                $this->pruneOldBackups($disk, $remoteDir, $retentionDays);
            } catch (Throwable $e) {
                $this->warn('Backup uploaded but retention cleanup failed: '.$e->getMessage());
            }
        }

        $this->info("Backup complete: disk={$disk} path={$remoteKey}");

        return self::SUCCESS;
    }

    private function resolveSqliteDatabasePath(string $configured): string
    {
        if ($configured === ':memory:' ||
            str_contains($configured, '?mode=memory') ||
            str_contains($configured, '&mode=memory')) {
            throw new RuntimeException('SQLite backups require a file-based database (not :memory:).');
        }

        $path = realpath($configured) ?: realpath(base_path($configured));

        if ($path === false) {
            throw new RuntimeException("SQLite database file does not exist at configured path: {$configured}");
        }

        return $path;
    }

    /**
     * Single-quoted SQL literal for VACUUM INTO, using forward slashes for SQLite on Windows.
     */
    private function sqliteVacuumPathLiteral(string $absolutePath): string
    {
        $normalized = str_replace('\\', '/', $absolutePath);

        return "'".str_replace("'", "''", $normalized)."'";
    }

    private function gzipFile(string $source, string $destination): void
    {
        $in = fopen($source, 'rb');
        if ($in === false) {
            throw new RuntimeException('Could not open SQLite backup for compression.');
        }

        $out = gzopen($destination, 'wb9');
        if ($out === false) {
            fclose($in);

            throw new RuntimeException('Could not open gzip destination for writing.');
        }

        try {
            while (! feof($in)) {
                $chunk = fread($in, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('Failed while reading SQLite backup for compression.');
                }
                gzwrite($out, $chunk);
            }
        } finally {
            gzclose($out);
            fclose($in);
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function cleanupLocal(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function pruneOldBackups(string $disk, string $remoteDir, int $retentionDays): void
    {
        $files = $remoteDir !== ''
            ? Storage::disk($disk)->files($remoteDir)
            : Storage::disk($disk)->files();

        $cutoff = now()->subDays($retentionDays);

        foreach ($files as $file) {
            $name = basename($file);
            if (! preg_match('/^contextual-console-(\d{4}-\d{2}-\d{2}-\d{6})\.sqlite\.gz$/', $name, $m)) {
                continue;
            }

            $fileTime = Carbon::createFromFormat('Y-m-d-His', $m[1], config('app.timezone'));

            if ($fileTime !== false && $fileTime->lt($cutoff)) {
                Storage::disk($disk)->delete($file);
            }
        }
    }
}
