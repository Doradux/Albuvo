<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function googleProfile(
        string $id = 'google-identified-123',
        string $email = 'google.person@example.test',
        bool $verified = true,
    ): void {
        $profile = (new GoogleUser)->setRaw(['email_verified' => $verified])
            ->map(['id' => $id, 'name' => 'Persona Google', 'email' => $email]);
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->once()->andReturn($profile);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($driver);
    }

    public function test_google_signup_requires_previously_accepted_terms(): void
    {
        $this->postJson('/api/v1/auth/google/prepare', ['accept_terms' => false])
            ->assertUnprocessable();
        $this->withSession(['google_oauth_mode' => 'signup']);
        // No profile fetch when consent is missing.
        $this->googleProfile();
        $this->get('/api/v1/auth/google/callback')
            ->assertRedirect('http://localhost:5174/signup?google_error=accept_terms');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_signup_creates_verified_account_with_consent(): void
    {
        $this->postJson('/api/v1/auth/google/prepare', ['accept_terms' => true])
            ->assertOk()->assertJsonPath('ready', true);
        $this->withSession(['google_oauth_mode' => 'signup']);
        $this->googleProfile();
        $this->get('/api/v1/auth/google/callback')
            ->assertRedirect('http://localhost:5174/app?google=connected');

        $user = User::where('google_id', 'google-identified-123')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('google.person@example.test', $user->email);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertDatabaseHas('consent_acceptances', [
            'user_id' => $user->id,
            'document_type' => 'terms_and_rules',
        ]);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.google_connected', true);
    }

    public function test_google_login_works_for_an_already_linked_account(): void
    {
        $user = User::factory()->create(['google_id' => 'google-identified-123']);
        $this->withSession(['google_oauth_mode' => 'login']);
        $this->googleProfile();
        $this->get('/api/v1/auth/google/callback')
            ->assertRedirect('http://localhost:5174/app?google=connected');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_google_login_cannot_autocreate_new_user_without_consent(): void
    {
        $this->withSession(['google_oauth_mode' => 'login']);
        $this->googleProfile();
        $this->get('/api/v1/auth/google/callback')
            ->assertRedirect('http://localhost:5174/login?google_error=signup_required');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_matching_email_does_not_silently_link_existing_password_account(): void
    {
        $existing = User::factory()->create(['email' => 'google.person@example.test']);
        $this->withSession(['google_oauth_mode' => 'signup', 'google_signup_terms' => true]);
        $this->googleProfile();
        $this->get('/api/v1/auth/google/callback')
            ->assertRedirect('http://localhost:5174/login?google_error=existing_account');
        $this->assertNull($existing->fresh()->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_authenticated_user_can_link_same_google_email(): void
    {
        $existing = User::factory()->create(['email' => 'google.person@example.test']);
        $this->actingAs($existing)->withSession(['google_oauth_mode' => 'link']);
        $this->googleProfile();
        $this->get('/api/v1/auth/google/callback')
            ->assertRedirect('http://localhost:5174/app?google=linked');
        $this->assertSame('google-identified-123', $existing->fresh()->google_id);
        $this->assertAuthenticatedAs($existing);
    }

    public function test_linking_rejects_other_email_or_other_user_google_identity(): void
    {
        $existing = User::factory()->create(['email' => 'google.person@example.test']);
        $this->actingAs($existing)->withSession(['google_oauth_mode' => 'link']);
        $this->googleProfile('other-google', 'different@example.test');
        $this->get('/api/v1/auth/google/callback')
            ->assertRedirect('http://localhost:5174/app?google_error=different_email');
        $this->assertNull($existing->fresh()->google_id);

        User::factory()->create(['google_id' => 'already-used']);
        $this->withSession(['google_oauth_mode' => 'link']);
        $this->googleProfile('already-used');
        $this->get('/api/v1/auth/google/callback')
            ->assertRedirect('http://localhost:5174/app?google_error=identity_in_use');
        $this->assertNull($existing->fresh()->google_id);
    }

    public function test_unverified_google_address_is_never_accepted(): void
    {
        $this->withSession(['google_oauth_mode' => 'signup', 'google_signup_terms' => true]);
        $this->googleProfile(verified: false);
        $this->get('/api/v1/auth/google/callback')
            ->assertRedirect('http://localhost:5174/login?google_error=unverified_email');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_callback_without_session_state_never_authenticates(): void
    {
        $this->get('/api/v1/auth/google/callback')
            ->assertRedirect('http://localhost:5174/login?google_error=session_expired');
        $this->assertGuest();
    }

    public function test_oauth_redirect_requests_state_and_exact_local_callback(): void
    {
        config()->set('services.google.client_id', 'dummy-client-for-test');
        config()->set('services.google.client_secret', 'dummy-secret-for-test');
        config()->set('services.google.redirect', 'http://localhost:5174/api/v1/auth/google/callback');
        $response = $this->get('/api/v1/auth/google/redirect?intent=login')->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertSame('accounts.google.com', parse_url($url, PHP_URL_HOST));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
        $this->assertSame('dummy-client-for-test', $params['client_id']);
        $this->assertSame('http://localhost:5174/api/v1/auth/google/callback', $params['redirect_uri']);
        $this->assertNotEmpty($params['state']);
        $this->assertSame('login', session('google_oauth_mode'));
    }

    public function test_signup_redirect_requires_explicit_terms(): void
    {
        config()->set('services.google.client_id', 'dummy-client-for-test');
        config()->set('services.google.client_secret', 'dummy-secret-for-test');
        $this->get('/api/v1/auth/google/redirect?intent=signup')
            ->assertRedirect('http://localhost:5174/signup?google_error=accept_terms');
    }

    public function test_google_callback_rejects_tampered_oauth_state(): void
    {
        config()->set('services.google.client_id', 'dummy-client-for-test');
        config()->set('services.google.client_secret', 'dummy-secret-for-test');
        $this->withSession(['google_oauth_mode' => 'login', 'state' => 'expected-state']);
        // Socialite rejects this before exchanging the code with Google.
        $this->get('/api/v1/auth/google/callback?code=dummy&state=attacker-state')
            ->assertRedirect('http://localhost:5174/login?google_error=oauth_failed');
        $this->assertGuest();
    }

    public function test_unconfigured_provider_gives_clear_error(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);
        $this->get('/api/v1/auth/google/redirect')
            ->assertRedirect('http://localhost:5174/login?google_error=not_configured');
    }
}
