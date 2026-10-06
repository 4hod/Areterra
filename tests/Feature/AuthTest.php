<?php

namespace Tests\Feature;

use App\Http\Controllers\MicrosoftAuthController;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_user_can_log_in_and_reach_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'password' => 'secret-password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertOk();
    }

    public function test_user_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'wrong',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'new-password-456'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_repeated_attempts_against_one_account_are_limited_across_ip_addresses(): void
    {
        $user = User::factory()->create(['password' => 'correct-password-123']);

        foreach (range(1, 10) as $attempt) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
                ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->post('/login', ['email' => $user->email, 'password' => 'correct-password-123'])
            ->assertSessionHasErrors([
                'email' => 'Too many sign-in attempts. Please wait 15 minutes and try again.',
            ]);

        $this->assertGuest();
    }

    public function test_security_headers_are_added_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->get('https://localhost/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_microsoft_sign_in_requires_tenant_specific_complete_configuration(): void
    {
        $clientId = '11111111-2222-4333-8444-555555555555';

        $this->assertFalse(MicrosoftAuthController::validTenant('common'));
        $this->assertFalse(MicrosoftAuthController::validTenant('admin@example.com'));
        $this->assertTrue(MicrosoftAuthController::validTenant('example.onmicrosoft.com'));
        $this->assertFalse(MicrosoftAuthController::validClientId('not-a-uuid'));
        $this->assertTrue(MicrosoftAuthController::validClientId($clientId));

        Setting::set('ms_client_id', $clientId);
        Setting::set('ms_tenant_id', 'example.onmicrosoft.com');
        $this->assertFalse(MicrosoftAuthController::configured());

        Setting::set('ms_client_secret', encrypt('********'));
        $this->assertFalse(MicrosoftAuthController::configured());

        Setting::set('ms_client_secret', encrypt('a-realistic-client-secret-value'));
        $this->assertTrue(MicrosoftAuthController::configured());
    }
}
