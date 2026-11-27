<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use ZipArchive;

class BackupService
{
    public function backupDirectory(): string
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    public function listBackups(): array
    {
        $dir = $this->backupDirectory();
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.zip') ?: [];
        $list = [];
        foreach ($files as $f) {
            $list[] = [
                'filename' => basename($f),
                'size' => filesize($f),
                'size_human' => $this->formatBytes(filesize($f)),
                'created_at' => date('Y-m-d H:i:s', filemtime($f)),
                'mtime' => filemtime($f),
            ];
        }
        usort($list, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $list;
    }

    public function createBackup(string $prefix = 'nuseirat_backup'): string
    {
        $filename = $prefix . '_' . now()->format('Ymd_His') . '.zip';
        $zipPath = $this->backupDirectory() . DIRECTORY_SEPARATOR . $filename;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('تعذر إنشاء ملف النسخة الاحتياطية.');
        }

        $connection = config('database.default');
        $manifest = [
            'app' => config('app.name'),
            'created_at' => now()->toIso8601String(),
            'db_connection' => $connection,
        ];

        if ($connection === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            if ($dbPath && file_exists($dbPath)) {
                // Ensure WAL checkpoint so sqlite file is fully consistent
                try {
                    DB::statement('PRAGMA wal_checkpoint(FULL)');
                } catch (\Throwable) {
                }
                $zip->addFile($dbPath, 'database/database.sqlite');
            }
        } else {
            // Portable JSON dump fallback for MySQL/PostgreSQL
            $tables = [
                'planning_years', 'departments', 'users', 'main_objectives',
                'sub_objectives', 'programs', 'operational_plans', 'activities',
                'activity_versions', 'monthly_followups', 'budget_allocations',
                'attachments', 'activity_comments', 'internal_messages',
                'system_notifications', 'risk_obstacles', 'audit_logs',
            ];
            $dump = [];
            foreach ($tables as $t) {
                try {
                    $dump[$t] = DB::table($t)->get()->toArray();
                } catch (\Throwable) {
                }
            }
            $zip->addFromString('database/dump.json', json_encode($dump, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        // Include uploaded attachments
        $attachDir = storage_path('app/private/attachments');
        if (is_dir($attachDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($attachDir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $rel = 'attachments/' . str_replace('\\', '/', substr($file->getPathname(), strlen($attachDir) + 1));
                    $zip->addFile($file->getPathname(), $rel);
                }
            }
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $zip->close();

        return $filename;
    }

    public function restoreBackup(string $zipPath): void
    {
        if (!file_exists($zipPath)) {
            throw new \RuntimeException('ملف النسخة الاحتياطية غير موجود.');
        }

        // Safety pre-restore backup
        $this->createBackup('pre_restore_safety');

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('ملف النسخة الاحتياطية تالف أو غير صالح.');
        }

        $connection = config('database.default');
        if ($connection === 'sqlite') {
            $sqliteContent = $zip->getFromName('database/database.sqlite');
            if ($sqliteContent === false) {
                $zip->close();
                throw new \RuntimeException('النسخة الاحتياطية لا تحتوي على قاعدة بيانات SQLite صالحة.');
            }
            $dbPath = config('database.connections.sqlite.database');
            DB::disconnect();
            // Remove WAL/SHM stale files if present
            foreach ([$dbPath . '-wal', $dbPath . '-shm'] as $wal) {
                if (file_exists($wal)) {
                    @unlink($wal);
                }
            }
            file_put_contents($dbPath, $sqliteContent);
            DB::reconnect();
        } else {
            $dumpRaw = $zip->getFromName('database/dump.json');
            if ($dumpRaw === false) {
                $zip->close();
                throw new \RuntimeException('ملف النسخة الاحتياطية لا يحتوي على بيانات JSON للاستعادة.');
            }
            $dump = json_decode($dumpRaw, true) ?: [];
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($dump as $table => $rows) {
                DB::table($table)->truncate();
                if (!empty($rows)) {
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table($table)->insert(array_map(fn ($r) => (array) $r, $chunk));
                    }
                }
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        // Restore attachments safely (prevent zip-slip)
        $attachBase = storage_path('app/private/attachments');
        if (!is_dir($attachBase)) {
            mkdir($attachBase, 0755, true);
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'attachments/') && !str_contains($name, '..')) {
                $rel = substr($name, strlen('attachments/'));
                if ($rel === '' || str_ends_with($rel, '/')) {
                    continue;
                }
                $target = $attachBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                $parent = dirname($target);
                if (!is_dir($parent)) {
                    mkdir($parent, 0755, true);
                }
                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    file_put_contents($target, $content);
                }
            }
        }

        $zip->close();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
