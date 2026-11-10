<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_and_restore_require_a_mysql_database_connection(): void
    {
        config(['database.default' => 'sqlite']);
        $service = new DatabaseBackupService;

        try {
            $service->create();
            $this->fail('A SQLite backup must not report success.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('MySQL', $exception->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $service->restore(new Backup);
    }

    public function test_restore_rejects_a_backup_with_a_mismatched_checksum(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/upload.sql', 'SELECT 1;');
        config(['database.default' => 'mysql']);

        $backup = new Backup([
            'disk' => 'local',
            'path' => 'backups/upload.sql',
            'status' => 'uploaded',
            'checksum' => str_repeat('0', 64),
        ]);

        try {
            (new DatabaseBackupService)->restore($backup);
            $this->fail('A backup with a mismatched checksum must not be restored.');
        } catch (RuntimeException $exception) {
            $this->assertSame('فشل التحقق من سلامة ملف النسخة الاحتياطية.', $exception->getMessage());
        } finally {
            config(['database.default' => 'sqlite']);
        }
    }
}
