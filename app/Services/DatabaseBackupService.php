<?php

namespace App\Services;

use App\Models\DatabaseBackup;
use App\Models\DatabaseRestore;
use App\Models\Setting;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DatabaseBackupService
{
    /**
     * Insert the row synchronously so callers (the Filament action, the
     * scheduler command) see it appear immediately — before a queued job
     * ever gets a chance to run mysqldump. Without this split, nothing is
     * visible at all until a worker happens to pick the job up, and if no
     * worker is running, nothing ever appears.
     */
    public function createPendingBackup(?int $userId, string $type = 'manual'): DatabaseBackup
    {
        $prefix = $type === 'pre_restore_safety' ? 'safety' : 'backup';
        $filename = "{$prefix}-".now()->format('Ymd_His').'.sql.gz';

        return DatabaseBackup::create([
            'filename' => $filename,
            'disk' => 'backups',
            'type' => $type,
            'status' => 'pending',
            'triggered_by' => $userId,
        ]);
    }

    public function runBackup(DatabaseBackup $backup): DatabaseBackup
    {
        $backup->update(['status' => 'running', 'started_at' => now()]);

        $credentialsFile = null;

        try {
            $credentialsFile = $this->writeCredentialsFile();
            $destPath = Storage::disk($backup->disk)->path($backup->filename);
            $database = config('database.connections.mysql.database');

            // These tables track backup/restore operations themselves — a
            // full dump would capture them too, and restoring it later
            // would overwrite them back to their state at dump time,
            // erasing any backups/restores tracked since. They must never
            // round-trip through their own backup.
            $command = sprintf(
                'mysqldump --defaults-extra-file=%s --ignore-table=%s --ignore-table=%s %s | gzip > %s',
                escapeshellarg($credentialsFile),
                escapeshellarg("{$database}.database_backups"),
                escapeshellarg("{$database}.database_restores"),
                escapeshellarg($database),
                escapeshellarg($destPath)
            );

            $result = Process::timeout(600)->run($command);

            if (! $result->successful()) {
                throw new \RuntimeException($result->errorOutput() ?: 'mysqldump exited with a non-zero status.');
            }

            $backup->update([
                'status' => 'completed',
                'size_bytes' => is_file($destPath) ? filesize($destPath) : null,
                'completed_at' => now(),
            ]);

            if (in_array($backup->type, ['manual', 'scheduled'], true)) {
                $this->pruneOldBackups();
            }
        } catch (Throwable $e) {
            $backup->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 2000),
                'completed_at' => now(),
            ]);
        } finally {
            if ($credentialsFile) {
                @unlink($credentialsFile);
            }
        }

        return $backup->fresh();
    }

    public function createBackup(?int $userId, string $type = 'manual'): DatabaseBackup
    {
        return $this->runBackup($this->createPendingBackup($userId, $type));
    }

    /**
     * Same immediate-visibility reasoning as createPendingBackup().
     */
    public function createPendingRestore(DatabaseBackup $backup, int $userId): DatabaseRestore
    {
        return DatabaseRestore::create([
            'database_backup_id' => $backup->id,
            'status' => 'pending',
            'restored_by' => $userId,
        ]);
    }

    public function runRestore(DatabaseRestore $restore): DatabaseRestore
    {
        $restore->update(['status' => 'running', 'started_at' => now()]);
        $backup = $restore->backup;

        $safetyBackup = $this->createBackup($restore->restored_by, 'pre_restore_safety');
        $restore->update(['safety_backup_id' => $safetyBackup->id]);

        if ($safetyBackup->status !== 'completed') {
            $restore->update([
                'status' => 'failed',
                'error_message' => 'Restore aborted: the automatic safety backup failed — '.$safetyBackup->error_message,
                'completed_at' => now(),
            ]);

            return $restore->fresh();
        }

        $credentialsFile = null;

        try {
            $credentialsFile = $this->writeCredentialsFile();
            $sourcePath = Storage::disk($backup->disk)->path($backup->filename);

            $command = sprintf(
                'gunzip < %s | mysql --defaults-extra-file=%s %s',
                escapeshellarg($sourcePath),
                escapeshellarg($credentialsFile),
                escapeshellarg(config('database.connections.mysql.database'))
            );

            $result = Process::timeout(900)->run($command);

            if (! $result->successful()) {
                throw new \RuntimeException($result->errorOutput() ?: 'mysql restore exited with a non-zero status.');
            }

            $restore->update(['status' => 'completed', 'completed_at' => now()]);
        } catch (Throwable $e) {
            $restore->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 2000),
                'completed_at' => now(),
            ]);
        } finally {
            if ($credentialsFile) {
                @unlink($credentialsFile);
            }
        }

        return $restore->fresh();
    }

    public function restore(DatabaseBackup $backup, int $userId): DatabaseRestore
    {
        return $this->runRestore($this->createPendingRestore($backup, $userId));
    }

    public function pruneOldBackups(): void
    {
        $keep = max(0, (int) Setting::get(Setting::BACKUP_RETENTION_COUNT, 14));

        $keepIds = DatabaseBackup::query()
            ->where('status', 'completed')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($keep)
            ->pluck('id');

        DatabaseBackup::query()
            ->where('status', 'completed')
            ->whereNotIn('id', $keepIds)
            ->get()
            ->each(function (DatabaseBackup $backup): void {
                Storage::disk($backup->disk)->delete($backup->filename);
                $backup->delete();
            });
    }

    private function writeCredentialsFile(): string
    {
        $config = config('database.connections.mysql');
        $path = tempnam(sys_get_temp_dir(), 'dbcnf');

        file_put_contents($path, sprintf(
            "[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n",
            $config['host'],
            $config['port'],
            $config['username'],
            $config['password']
        ));
        chmod($path, 0600);

        return $path;
    }
}
