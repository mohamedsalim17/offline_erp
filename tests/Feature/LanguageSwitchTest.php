<?php

namespace Tests\Feature;

use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    public function test_language_switch_stores_the_locale_and_redirects_to_admin(): void
    {
        $this->get('/lang/ar')
            ->assertRedirect('/admin')
            ->assertSessionHas('locale', 'ar');
    }

    public function test_locale_middleware_applies_the_saved_locale(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->get('/')
            ->assertOk();

        $this->assertSame('ar', app()->getLocale());
    }

    public function test_language_switch_rejects_unsupported_locales(): void
    {
        $this->get('/lang/fr')->assertNotFound();
    }
}
