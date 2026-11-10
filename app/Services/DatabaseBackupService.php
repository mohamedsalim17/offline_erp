<?php

namespace App\Services;

use App\Models\Backup;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    public function create(?int $createdBy = null, string $kind = 'manual'): Backup
    {
        $this->assertSupportedConnection();

        $disk = Storage::disk('local');
        $directory = 'backups';
        $disk->makeDirectory($directory);

        $filename = now()->format('Ymd_His').'_'.Str::lower(Str::random(8)).'.sql';
        $path = "{$directory}/{$filename}";
        $backup = Backup::create([
            'name' => "{$kind}_{$filename}",
            'disk' => 'local',
            'path' => $path,
            'status' => 'pending',
            'started_at' => now(),
            'created_by' => $createdBy,
        ]);

        $output = @fopen($disk->path($path), 'wb');

        if ($output === false) {
            $backup->update([
                'status' => 'failed',
                'error_message' => 'تعذر إنشاء ملف النسخة الاحتياطية.',
                'completed_at' => now(),
            ]);

            throw new RuntimeException('تعذر إنشاء ملف النسخة الاحتياطية.');
        }

        $defaultsFile = null;
        $stderr = '';

        try {
            $defaultsFile = $this->createClientDefaultsFile();
            $process = new Process([
                $this->binaryPath('mysqldump'),
                "--defaults-extra-file={$defaultsFile}",
                '--single-transaction',
                '--routines',
                '--triggers',
                '--hex-blob',
                (string) config('database.connections.mysql.database'),
            ]);
            $process->setTimeout(0);
            $process->run(function (string $type, string $buffer) use ($output, &$stderr): void {
                if ($type === Process::OUT) {
                    if (fwrite($output, $buffer) === false) {
                        throw new RuntimeException('تعذرت كتابة ملف النسخة الاحتياطية.');
                    }

                    return;
                }

                $stderr = substr($stderr.$buffer, -8000);
            });

            if (! $process->isSuccessful()) {
                throw new RuntimeException($this->safeProcessError($stderr));
            }

            if (! fflush($output)) {
                throw new RuntimeException('تعذر حفظ ملف النسخة الاحتياطية على القرص.');
            }

            $size = filesize($disk->path($path));
            $checksum = hash_file('sha256', $disk->path($path));

            if ($size === false || $checksum === false) {
                throw new RuntimeException('تعذر التحقق من ملف النسخة الاحتياطية بعد إنشائه.');
            }

            $backup->update([
                'status' => 'completed',
                'size_bytes' => $size,
                'checksum' => $checksum,
                'completed_at' => now(),
                'error_message' => null,
            ]);

            return $backup->fresh();
        } catch (Throwable $exception) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }

            $backup->update([
                'status' => 'failed',
                'error_message' => $this->safeProcessError($exception->getMessage()),
                'completed_at' => now(),
            ]);

            throw $exception;
        } finally {
            fclose($output);

            if ($defaultsFile && File::exists($defaultsFile)) {
                File::delete($defaultsFile);
            }
        }
    }

    public function restore(Backup $backup, ?int $createdBy = null): Backup
    {
        $this->assertSupportedConnection();

        if (! in_array($backup->status, ['completed', 'uploaded'], true)) {
            throw new RuntimeException('لا يمكن استعادة نسخة غير مكتملة أو فاشلة.');
        }

        $disk = Storage::disk($backup->disk);

        if (! $disk->exists($backup->path)) {
            throw new RuntimeException('ملف النسخة الاحتياطية غير موجود.');
        }

        $actualChecksum = hash_file('sha256', $disk->path($backup->path));

        if (! is_string($actualChecksum) || ! hash_equals((string) $backup->checksum, $actualChecksum)) {
            throw new RuntimeException('فشل التحقق من سلامة ملف النسخة الاحتياطية.');
        }

        $safetyBackup = $this->create($createdBy, 'before_restore');
        $defaultsFile = $this->createClientDefaultsFile();
        $stderr = '';
        $input = fopen($disk->path($backup->path), 'rb');

        if ($input === false) {
            File::delete($defaultsFile);
            throw new RuntimeException('تعذر فتح ملف النسخة الاحتياطية للاستعادة.');
        }

        try {
            $process = new Process([
                $this->binaryPath('mysql'),
                "--defaults-extra-file={$defaultsFile}",
                (string) config('database.connections.mysql.database'),
            ]);
            $process->setInput($input);
            $process->setTimeout(0);
            $process->run(function (string $type, string $buffer) use (&$stderr): void {
                if ($type === Process::ERR) {
                    $stderr = substr($stderr.$buffer, -8000);
                }
            });

            if (! $process->isSuccessful()) {
                throw new RuntimeException($this->safeProcessError($stderr));
            }

            return Backup::query()->updateOrCreate(
                ['id' => $safetyBackup->id],
                [
                    'name' => $safetyBackup->name,
                    'disk' => $safetyBackup->disk,
                    'path' => $safetyBackup->path,
                    'status' => 'completed',
                    'size_bytes' => $safetyBackup->size_bytes,
                    'checksum' => $safetyBackup->checksum,
                    'started_at' => $safetyBackup->started_at,
                    'completed_at' => $safetyBackup->completed_at,
                    'error_message' => null,
                    'created_by' => null,
                ],
            );
        } finally {
            fclose($input);

            if (File::exists($defaultsFile)) {
                File::delete($defaultsFile);
            }
        }
    }

    private function assertSupportedConnection(): void
    {
        if (config('database.default') !== 'mysql') {
            throw new RuntimeException('النسخ الاحتياطي والاستعادة متاحان حالياً لاتصال MySQL فقط.');
        }
    }

    private function binaryPath(string $binary): string
    {
        $configuredPath = config("database.backup_{$binary}_path");
        $candidates = array_filter([
            $configuredPath,
            base_path("..\\..\\mysql\\bin\\{$binary}.exe"),
            base_path("..\\mysql\\bin\\{$binary}.exe"),
            $binary,
        ]);

        foreach ($candidates as $candidate) {
            if (str_contains($candidate, '\\') || str_contains($candidate, '/')) {
                if (File::exists($candidate)) {
                    return $candidate;
                }

                continue;
            }

            return $candidate;
        }

        throw new RuntimeException("تعذر العثور على أداة MySQL المطلوبة: {$binary}.");
    }

    private function createClientDefaultsFile(): string
    {
        $connection = config('database.connections.mysql');
        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory);

        $path = $directory.DIRECTORY_SEPARATOR.'.mysql-'.Str::uuid().'.cnf';
        $lines = [
            '[client]',
            'host="'.$this->escapeOption((string) $connection['host']).'"',
            'port="'.$this->escapeOption((string) $connection['port']).'"',
            'user="'.$this->escapeOption((string) $connection['username']).'"',
            'password="'.$this->escapeOption((string) $connection['password']).'"',
            'default-character-set='.$this->escapeOption((string) ($connection['charset'] ?? 'utf8mb4')),
        ];

        if (! File::put($path, implode(PHP_EOL, $lines).PHP_EOL, true)) {
            throw new RuntimeException('تعذر إعداد اتصال أداة النسخ الاحتياطي.');
        }

        @chmod($path, 0600);

        return $path;
    }

    private function escapeOption(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]);
    }

    private function safeProcessError(string $message): string
    {
        $password = (string) config('database.connections.mysql.password');

        if ($password !== '') {
            $message = str_replace($password, '[hidden]', $message);
        }

        return mb_substr(trim($message) ?: 'فشلت عملية النسخ الاحتياطي أو الاستعادة.', 0, 2000);
    }
}
