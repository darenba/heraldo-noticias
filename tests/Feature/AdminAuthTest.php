<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Login page
    // -------------------------------------------------------------------------

    public function test_login_page_returns_200(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.login');
    }

    public function test_authenticated_user_redirected_from_login_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.login'));

        $response->assertRedirect(route('admin.dashboard'));
    }

    // -------------------------------------------------------------------------
    // Login POST
    // -------------------------------------------------------------------------

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = User::factory()->create([
            'email'    => 'admin@heraldo.local',
            'password' => bcrypt('secret123'),
            'role'     => 'admin',
        ]);

        $response = $this->post(route('admin.login'), [
            'email'    => 'admin@heraldo.local',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'admin@heraldo.local',
            'password' => bcrypt('secret123'),
            'role'     => 'admin',
        ]);

        $response = $this->post(route('admin.login'), [
            'email'    => 'admin@heraldo.local',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->post(route('admin.login'), [
            'email'    => 'nobody@example.com',
            'password' => 'anything',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_validates_email_format(): void
    {
        $response = $this->post(route('admin.login'), [
            'email'    => 'not-an-email',
            'password' => 'secret123',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_login_validates_password_required(): void
    {
        $response = $this->post(route('admin.login'), [
            'email' => 'admin@heraldo.local',
        ]);

        $response->assertSessionHasErrors('password');
    }

    // -------------------------------------------------------------------------
    // Admin middleware — protected routes
    // -------------------------------------------------------------------------

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
    }

    public function test_guest_cannot_access_editions_index(): void
    {
        $response = $this->get(route('admin.editions.index'));

        $response->assertRedirect(route('admin.login'));
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    public function test_admin_can_logout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.logout'));

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    // -------------------------------------------------------------------------
    // Rate limiting (throttle:5,1 on login)
    // -------------------------------------------------------------------------

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login'), [
                'email'    => 'admin@heraldo.local',
                'password' => 'wrong',
            ]);
        }

        $response = $this->post(route('admin.login'), [
            'email'    => 'admin@heraldo.local',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }
}
