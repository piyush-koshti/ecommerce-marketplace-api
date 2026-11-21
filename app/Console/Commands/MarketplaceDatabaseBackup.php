<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

class MarketplaceDatabaseBackup extends Command
{
    protected $signature = 'marketplace:db-backup';

    protected $description = 'Create a consistent MySQL logical backup in private application storage.';

    public function handle(): int
    {
        if (config('database.default') === '' || config('database.connections.'.config('database.default').'.driver') !== 'mysql') {
            $this->error('This backup command currently supports MySQL connections only.');

            return self::FAILURE;
        }

        $connection = config('database.connections.'.config('database.default'));
        foreach (['database', 'username', 'host'] as $required) {
            if (empty($connection[$required])) {
                $this->error('Database configuration is incomplete.');

                return self::FAILURE;
            }
        }

        $binary = (string) config('marketplace.backup.mysqldump_path', 'mysqldump');
        $relativeDirectory = 'backups';
        Storage::disk('local')->makeDirectory($relativeDirectory);
        $filename = 'marketplace-'.now()->format('Ymd-His-u').'.sql';
        $relativePath = $relativeDirectory.'/'.$filename;
        $outputPath = Storage::disk('local')->path($relativePath);
        $defaultsFile = tempnam(sys_get_temp_dir(), 'marketplace-mysql-');

        if ($defaultsFile === false) {
            $this->error('Unable to create temporary database client configuration.');

            return self::FAILURE;
        }

        try {
            $clientConfig = "[client]\n";
            $clientConfig .= 'user="'.$this->escapeOption((string) $connection['username'])."\"\n";
            $clientConfig .= 'password="'.$this->escapeOption((string) ($connection['password'] ?? ''))."\"\n";
            $clientConfig .= 'host="'.$this->escapeOption((string) $connection['host'])."\"\n";
            $clientConfig .= 'port="'.(int) ($connection['port'] ?? 3306)."\"\n";
            if (! empty($connection['unix_socket'])) {
                $clientConfig .= 'socket="'.$this->escapeOption((string) $connection['unix_socket'])."\"\n";
            }

            if (file_put_contents($defaultsFile, $clientConfig) === false) {
                $this->error('Unable to write temporary database client configuration.');

                return self::FAILURE;
            }
            @chmod($defaultsFile, 0600);

            $process = new Process([
                $binary,
                '--defaults-extra-file='.$defaultsFile,
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--result-file='.$outputPath,
                (string) $connection['database'],
            ]);
            $process->setTimeout(3600);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outputPath) || filesize($outputPath) === 0) {
                @unlink($outputPath);
                $this->error('Database backup failed. Check mysqldump availability and application logs.');

                return self::FAILURE;
            }

            $this->info('Database backup created: storage/app/private/'.$relativePath);
            $this->line('Size: '.number_format(filesize($outputPath)).' bytes');

            return self::SUCCESS;
        } catch (Throwable) {
            @unlink($outputPath);
            $this->error('Database backup failed. Check database connectivity and mysqldump configuration.');

            return self::FAILURE;
        } finally {
            @unlink($defaultsFile);
        }
    }

    private function escapeOption(string $value): string
    {
        return str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], $value);
    }
}
