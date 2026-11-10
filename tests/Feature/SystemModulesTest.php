<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_settings_and_backups_persist_with_expected_casts_and_relation(): void
    {
        $user = User::factory()->create();
        $setting = SystemSetting::create([
            'key' => 'company.name',
            'value' => 'Offline ERP',
            'type' => 'string',
            'group' => 'company',
            'is_public' => true,
        ]);
        $backup = $user->backups()->create([
            'name' => 'daily',
            'path' => 'backups/daily.sql',
            'status' => 'completed',
            'size_bytes' => 1024,
        ]);

        $this->assertTrue($setting->is_public);
        $this->assertSame($user->id, $backup->creator->id);
        $this->assertSame(1024, $backup->size_bytes);
        $this->assertInstanceOf(Backup::class, $user->backups()->first());
    }

    public function test_permissions_seeder_is_repeatable_and_assigns_role_permissions(): void
    {
        $seeder = new RolesAndPermissionsSeeder;
        $seeder->run();
        $seeder->run();

        $user = User::factory()->create();
        $user->assignRole('manager');

        $this->assertTrue($user->hasPermissionTo('products.update'));
        $this->assertFalse($user->hasPermissionTo('users.delete'));
        $this->assertSame(4, \Spatie\Permission\Models\Role::count());
    }

    public function test_successful_login_updates_login_counter_and_timestamp(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'login_count' => 1,
        ]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }
}
