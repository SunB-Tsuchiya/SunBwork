<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'mysql接続（sunbwork）をmysqldumpでgzip圧縮しstorage/app/backupsに保存する（月・水・金の3世代を上書きローテーション）';

    public function handle(): int
    {
        $connection = config('database.connections.mysql');

        $slot = match (now()->isoWeekday()) {
            1 => 'mon',
            3 => 'wed',
            5 => 'fri',
            default => now()->format('N'),
        };

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $path = "{$directory}/db_backup_{$slot}.sql.gz";
        $tmpPath = "{$path}.tmp";

        $process = new Process([
            'mysqldump',
            '--host=' . $connection['host'],
            '--port=' . $connection['port'],
            '--user=' . $connection['username'],
            '--single-transaction',
            '--quick',
            $connection['database'],
        ]);
        $process->setEnv(['MYSQL_PWD' => $connection['password']]);
        $process->setTimeout(1800);

        $gz = gzopen($tmpPath, 'wb9');

        try {
            $process->run(function (string $type, string $buffer) use ($gz): void {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buffer);
                }
            });
        } finally {
            gzclose($gz);
        }

        if (! $process->isSuccessful()) {
            @unlink($tmpPath);

            throw new ProcessFailedException($process);
        }

        rename($tmpPath, $path);

        $sizeMb = round(filesize($path) / 1024 / 1024, 2);
        $this->info("バックアップ完了: {$path} ({$sizeMb} MB)");
        Log::info("[backup:database] バックアップ完了: {$path} ({$sizeMb} MB)");

        return self::SUCCESS;
    }
}
