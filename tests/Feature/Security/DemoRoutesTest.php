<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Vuexy demo scaffold used to be registered for everybody, including a `user-list` CRUD resource that could create
 * and delete users with no login at all. It must not exist unless the developer switched it on for local work.
 */
class DemoRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_user_crud_is_gone(): void
    {
        $this->getJson('/user-list')->assertNotFound();
        $this->postJson('/user-list', ['name' => 'x', 'email' => 'x@example.com'])->assertNotFound();
        $this->deleteJson('/user-list/1')->assertNotFound();
        $this->get('/laravel/user-management')->assertNotFound();
    }

    public function test_the_template_pages_are_not_served_to_visitors(): void
    {
        foreach (['/forms/basic-inputs', '/layouts/blank', '/app/email', '/app/ecommerce/dashboard', '/pages/misc-error', '/auth/login-basic', '/tables/datatables-basic', '/dashboard/analytics'] as $path) {
            $this->get($path)->assertNotFound();
        }

        $this->assertFalse(Route::has('forms-basic-inputs'));
        $this->assertFalse(Route::has('dashboard-analytics'));
    }

    public function test_the_real_routes_and_the_language_switch_are_still_there(): void
    {
        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('dashboard'));
        $this->get('/')->assertRedirect('/login');
        $this->get('/lang/ar')->assertRedirect();
    }

    public function test_the_language_switch_does_not_bounce_to_another_site(): void
    {
        $this->from('https://evil.example/landing')->get('/lang/ar')->assertRedirect(url('/'));
        $this->from('http://localhost/app/user/list')->get('/lang/en')->assertRedirect('http://localhost/app/user/list');
    }
}
