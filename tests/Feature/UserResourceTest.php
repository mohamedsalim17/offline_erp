<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_resource_has_a_discoverable_index_and_form_fields(): void
    {
        $form = UserResource::form(Schema::make());

        $this->assertSame(
            \App\Filament\Resources\UserResource\Pages\ManageUsers::class,
            UserResource::getPages()['index']->getPage(),
        );
        $this->assertCount(4, $form->getComponents());
    }

    public function test_guest_auth_redirect_uses_the_filament_login_route(): void
    {
        $this->get(route('pos.index'))
            ->assertRedirect(route('filament.admin.auth.login'));
    }
}
