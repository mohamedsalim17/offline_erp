<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class SystemSettingsPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['app.env' => 'local']);

        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);
    }

    public function test_system_settings_page_is_discoverable_and_shows_all_management_sections(): void
    {
        $this->get(route('filament.admin.pages.system-settings'))
            ->assertOk()
            ->assertSee('اللغة والهوية المؤسسية')
            ->assertSee('النسخ الاحتياطي والاستعادة')
            ->assertSee('رفع ملف SQL')
            ->assertSee('إعدادات نقاط البيع');

        $this->assertSame(
            \App\Filament\Pages\SystemSettings::class,
            \App\Filament\Pages\SystemSettings::getNavigationItems()[0]->getKey(),
        );
    }

    public function test_company_identity_locale_and_private_logo_are_saved_and_used_by_filament(): void
    {
        Storage::fake('local');
        $logo = UploadedFile::fake()->image('company-logo.png');

        $this->put(route('system-settings.update'), [
            'company_name' => 'شركة تجريبية',
            'locale' => 'ar',
            'company_address' => 'بغداد',
            'company_phone' => '07700000000',
            'company_email' => 'company@example.com',
            'company_logo' => $logo,
        ])->assertRedirect(route('filament.admin.pages.system-settings'));

        $this->assertDatabaseHas('system_settings', [
            'key' => 'company.name',
            'value' => 'شركة تجريبية',
        ]);
        $this->assertDatabaseHas('system_settings', [
            'key' => 'system.locale',
            'value' => 'ar',
        ]);

        $logoPath = SystemSetting::where('key', 'company.logo_path')->value('value');
        $this->assertNotNull($logoPath);
        Storage::disk('local')->assertExists($logoPath);

        $this->get(route('company.logo'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('filament.admin.pages.system-settings'))
            ->assertOk()
            ->assertSee('شركة تجريبية');

        $this->get('/admin')
            ->assertOk()
            ->assertSee('شركة تجريبية');
    }

    public function test_uploaded_sql_backup_is_stored_privately_with_checksum_and_can_be_downloaded(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('offline-backup.sql', 'CREATE TABLE restored (id INT);');

        $this->post(route('system-settings.backups.upload'), ['backup_file' => $file])
            ->assertRedirect();

        $backup = Backup::firstOrFail();
        $this->assertSame('uploaded', $backup->status);
        $this->assertNotNull($backup->checksum);
        Storage::disk('local')->assertExists($backup->path);

        $this->get(route('system-settings.backups.download', $backup))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_backup_creation_and_restore_actions_are_authorized_and_report_success(): void
    {
        $createdBackup = Backup::create([
            'name' => 'before_restore.sql',
            'disk' => 'local',
            'path' => 'backups/before_restore.sql',
            'status' => 'completed',
            'checksum' => str_repeat('a', 64),
            'created_by' => auth()->id(),
        ]);
        $safetyBackup = $createdBackup;

        $service = Mockery::mock(DatabaseBackupService::class);
        $service->shouldReceive('create')->once()->andReturn($createdBackup);
        $service->shouldReceive('restore')->once()->withArgs(
            fn (Backup $backup, int $userId): bool => $backup->is($createdBackup) && $userId === auth()->id(),
        )->andReturn($safetyBackup);
        $this->app->instance(DatabaseBackupService::class, $service);

        $this->post(route('system-settings.backups.create'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->post(route('system-settings.backups.restore', $createdBackup), [
            'confirm_restore' => '1',
        ])->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_users_without_settings_permission_cannot_open_system_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $this->actingAs($user)
            ->get(route('filament.admin.pages.system-settings'))
            ->assertForbidden();
    }

    public function test_manager_role_can_view_and_update_system_settings(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->get(route('filament.admin.pages.system-settings'))
            ->assertOk();

        $this->actingAs($manager)
            ->put(route('system-settings.update'), [
                'company_name' => 'متجر المدير',
                'locale' => 'en',
            ])
            ->assertRedirect(route('filament.admin.pages.system-settings'));
    }
}
