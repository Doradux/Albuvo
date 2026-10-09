<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller {
    public function csrf(): array { return ['csrf_token'=>csrf_token()]; }
    public function me(Request $r): array { return ['user'=>$r->user()?->only(['id','name','email','email_verified_at'])]; }
    public function register(Request $r) {
        $v=$r->validate([
            'name'=>'required|string|min:2|max:80',
            'email'=>'required|email:rfc|max:255|unique:users,email',
            'password'=>['required','confirmed',Password::min(10)->letters()->numbers()],
            'accept_terms'=>'accepted',
        ]);
        $user=DB::transaction(function() use ($v) {
            $u=User::create(['name'=>$v['name'],'email'=>mb_strtolower($v['email']),'password'=>$v['password']]);
            DB::table('consent_acceptances')->insert([
                'id'=>(string)\Illuminate\Support\Str::uuid(), 'user_id'=>$u->id,
                'document_type'=>'terms_and_rules', 'version'=>'draft-2026-10',
                'accepted_at'=>now(),
            ]);
            return $u;
        });
        Auth::login($user); $r->session()->regenerate();
        $user->sendEmailVerificationNotification();
        return response()->json(['user'=>$user->only(['id','name','email','email_verified_at']), 'verification_required'=>true],201);
    }
    public function login(Request $r) {
        $data=$r->validate(['email'=>'required|email','password'=>'required|string']);
        if (!Auth::attempt($data)) {
            return response()->json(['message'=>'Las credenciales no son válidas.'],422);
        }
        $r->session()->regenerate();
        return ['user'=>$r->user()->only(['id','name','email','email_verified_at'])];
    }
    public function logout(Request $r) {
        Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken();
        return response()->noContent();
    }
    public function resend(Request $r) {
        if(!$r->user()->hasVerifiedEmail()) $r->user()->sendEmailVerificationNotification();
        return ['message'=>'Revisa tu correo de verificación.'];
    }
}