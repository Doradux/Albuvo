<?php
use Illuminate\Support\Facades\Route;
Route::get('/api/v1/health', fn () => ['status' => 'ok']);
use App\Http\Controllers\AuthController as AuthC;
use App\Http\Controllers\GoogleAuthController as GoogleAuth;
use App\Http\Controllers\AlbumController as Albums;
use App\Http\Controllers\MediaController as MediaC;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
Route::prefix('api/v1')->group(function () {
    Route::get('/auth/csrf',[AuthC::class,'csrf']);
    Route::post('/auth/google/prepare',[GoogleAuth::class,'prepareSignup'])->middleware('throttle:10,1');
    Route::get('/auth/google/redirect',[GoogleAuth::class,'redirect'])->middleware('throttle:20,1');
    Route::get('/auth/google/callback',[GoogleAuth::class,'callback'])->middleware('throttle:20,1');
    Route::get('/me',[AuthC::class,'me']);
    Route::post('/auth/register',[AuthC::class,'register'])->middleware('throttle:5,1');
    Route::post('/auth/login',[AuthC::class,'login'])->middleware('throttle:10,1');
    Route::post('/album-invites/preview',[Albums::class,'previewInvite']);
    Route::post('/album-invites/resolve',[Albums::class,'acceptInvite'])->middleware('throttle:10,1');
    Route::get('/albums/{album}',[Albums::class,'show']);
    Route::get('/albums/{album}/media',[MediaC::class,'index']);
    Route::get('/albums/{album}/moderation',[MediaC::class,'moderation']);
    Route::post('/albums/{album}/uploads',[MediaC::class,'init'])->middleware('throttle:30,1');
    Route::post('/uploads/{session}/complete',[MediaC::class,'complete'])->middleware('throttle:30,1');
    Route::get('/uploads/{session}',[MediaC::class,'uploadStatus']);
    Route::post('/reports',[MediaC::class,'report'])->middleware('throttle:5,1');
    Route::middleware('auth')->group(function () {
        Route::post('/auth/logout',[AuthC::class,'logout']);
        Route::post('/auth/resend',[AuthC::class,'resend'])->middleware('throttle:3,1');
        Route::get('/albums',[Albums::class,'index']);
        Route::post('/albums',[Albums::class,'store']);
        Route::patch('/albums/{album}',[Albums::class,'update']);
        Route::get('/albums/{album}/members',[Albums::class,'members']);
        Route::delete('/albums/{album}/members/{member}',[Albums::class,'removeMember']);
        Route::get('/albums/{album}/invites',[Albums::class,'invites']);
        Route::post('/albums/{album}/invites',[Albums::class,'createInvite']);
        Route::delete('/albums/{album}/invites/{invite}',[Albums::class,'revokeInvite']);
        Route::post('/albums/{album}/members',[Albums::class,'addMember']);
        Route::post('/media/{media}/approve',[MediaC::class,'approve']);
        Route::post('/media/{media}/reject',[MediaC::class,'reject']);
    });
});
Route::get('/api/v1/verify-email/{id}/{hash}',function(EmailVerificationRequest $request) {
    $request->fulfill();
    return redirect(rtrim(env('FRONTEND_URL','http://localhost:5174'),'/').'/app?verified=1');
})->middleware(['auth','signed'])->name('verification.verify');
