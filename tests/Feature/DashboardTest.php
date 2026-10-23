<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_are_sent_to_their_role_dashboard()
    {
        $user = User::factory()->withRole(Role::Karyawan)->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('me.dashboard', absolute: false));
        $this->get(route('me.dashboard'))->assertOk();
    }

    public function test_users_without_a_role_are_forbidden()
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertForbidden();
    }
}
