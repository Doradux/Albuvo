<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    private function frontend(string $path): RedirectResponse
    {
        return redirect(rtrim(env('FRONTEND_URL', 'http://localhost:5174'), '/') . $path);
    }

    private function error(string $reason, string $page = '/login'): RedirectResponse
    {
        return $this->frontend($page . '?google_error=' . rawurlencode($reason));
    }

    public function prepareSignup(Request $request): array
    {
        $request->validate(['accept_terms' => 'accepted']);
        // A completed OAuth flow must never silently create an account
        // without explicit acceptance of the current draft terms.
        $request->session()->put('google_signup_terms', true);
        return ['ready' => true];
    }

    public function redirect(Request $request)
    {
        $mode = $request->query('intent', 'login');
        abort_unless(in_array($mode, ['login', 'signup', 'link'], true), 404);
        if (!config('services.google.client_id') || !config('services.google.client_secret')) {
            return $this->error('not_configured', $mode === 'signup' ? '/signup' : '/login');
        }

        if ($mode === 'signup' && !$request->session()->get('google_signup_terms')) {
            return $this->error('accept_terms', '/signup');
        }
        if ($mode === 'link' && !$request->user()) {
            return $this->error('login_required');
        }

        // Socialite stores an unpredictable state in this SAME session;
        // never use stateless() for this browser-based OAuth flow.
        $request->session()->put('google_oauth_mode', $mode);
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $mode = $request->session()->pull('google_oauth_mode');
        $accepted = $request->session()->pull('google_signup_terms', false);
        if (!in_array($mode, ['login', 'signup', 'link'], true)) {
            return $this->error('session_expired');
        }
        if ($request->filled('error')) {
            return $this->error('cancelled', $mode === 'signup' ? '/signup' : '/login');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable $exception) {
            Log::warning('Google OAuth callback failed', ['exception' => $exception::class]);
            return $this->error('oauth_failed', $mode === 'signup' ? '/signup' : '/login');
        }

        $id = (string) $google->getId();
        $email = mb_strtolower(trim((string) $google->getEmail()));
        $raw = $google->getRaw();
        if ($id === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            ($raw['email_verified'] ?? false) !== true) {
            return $this->error('unverified_email');
        }

        // Linking is only possible from an authenticated session and with
        // an email proven to be the same as the existing local account.
        if ($mode === 'link') {
            $current = $request->user();
            if (!$current) return $this->error('login_required');
            if (mb_strtolower($current->email) !== $email) {
                return $this->error('different_email', '/app');
            }
            if (($current->google_id && $current->google_id !== $id) ||
                User::where('google_id', $id)->where('id','!=',$current->id)->exists()) {
                return $this->error('identity_in_use', '/app');
            }
            $current->forceFill(['google_id' => $id])->save();
            return $this->frontend('/app?google=linked');
        }

        $user = User::where('google_id', $id)->first();

        if (!$user) {
            if ($mode !== 'signup') {
                return $this->error('signup_required');
            }
            if (!$accepted) {
                return $this->error('accept_terms', '/signup');
            }
            // Never merge existing password accounts based solely on email.
            if (User::where('email', $email)->exists()) {
                return $this->error('existing_account', '/login');
            }

            try {
                $user = DB::transaction(function () use ($google, $id, $email) {
                    $newUser = User::create([
                        'name' => Str::limit(trim((string) $google->getName()) ?: explode('@', $email)[0], 80, ''),
                        'email' => $email,
                        // Keep password login disabled in practice, until
                        // the user explicitly configures a password.
                        'password' => Str::random(64),
                        'google_id' => $id,
                    ]);
                    $newUser->forceFill(['email_verified_at' => now()])->save();
                    DB::table('consent_acceptances')->insert([
                        'id' => (string) Str::uuid(),
                        'user_id' => $newUser->id,
                        'document_type' => 'terms_and_rules',
                        'version' => 'draft-2026-10',
                        'accepted_at' => now(),
                    ]);
                    return $newUser;
                });
            } catch (\Throwable $exception) {
                Log::warning('Google registration failed', ['exception' => $exception::class]);
                return $this->error('signup_failed', '/signup');
            }
        }

        Auth::login($user);
        $request->session()->regenerate();
        return $this->frontend('/app?google=connected');
    }
}
