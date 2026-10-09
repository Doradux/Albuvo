<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\AlbumMember;
use App\Models\Media;
use App\Models\UploadSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UploadLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function ownerAlbum(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $album = Album::create([
            'owner_id' => $owner->id,
            'slug' => 'lifecycle-' . Str::random(12),
            'title' => 'Album de prueba',
            'allow_guest_upload' => true,
            'quota_bytes' => 1300,
        ]);
        AlbumMember::create(['album_id' => $album->id, 'user_id' => $owner->id, 'role' => 'owner']);
        return [$owner, $album];
    }

    public function test_upload_retries_do_not_reserve_quota_twice(): void
    {
        [$owner, $album] = $this->ownerAlbum();
        $payload = [
            'filename' => 'prueba.jpg',
            'mime' => 'image/jpeg',
            'size' => 1200,
            'idempotency_key' => (string) Str::uuid(),
        ];
        $first = $this->actingAs($owner)->postJson("/api/v1/albums/{$album->id}/uploads", $payload)
            ->assertCreated()->json('upload_id');
        $second = $this->actingAs($owner)->postJson("/api/v1/albums/{$album->id}/uploads", $payload)
            ->assertCreated()->json('upload_id');
        $this->assertSame($first, $second);
        $this->assertSame(1200, $album->fresh()->reserved_bytes);
        $this->actingAs($owner)->postJson("/api/v1/albums/{$album->id}/uploads", [
            ...$payload,
            'size' => 1000,
        ])->assertStatus(409);
        $this->actingAs($owner)->postJson("/api/v1/albums/{$album->id}/uploads", [
            ...$payload,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(409);
    }

    public function test_expired_uploads_release_quota_and_remove_ingest(): void
    {
        Storage::fake('s3');
        [$owner, $album] = $this->ownerAlbum();
        $album->update(['reserved_bytes' => 900]);
        $media = Media::create([
            'album_id' => $album->id,
            'user_id' => $owner->id,
            'original_filename' => 'abandonada.jpg',
        ]);
        $session = UploadSession::create([
            'album_id' => $album->id,
            'media_id' => $media->id,
            'user_id' => $owner->id,
            'object_key' => 'ingest/expired.jpg',
            'idempotency_key' => (string) Str::uuid(),
            'declared_size' => 900,
            'reserved_bytes' => 900,
            'declared_mime' => 'image/jpeg',
            'expires_at' => now()->subMinute(),
        ]);
        Storage::disk('s3')->put($session->object_key, 'un archivo abandonado');
        $this->artisan('albuvo:cleanup-uploads')->assertSuccessful();
        $this->assertSame(0, $album->fresh()->reserved_bytes);
        $this->assertSame('expired', $session->fresh()->status);
        $this->assertSame('failed', $media->fresh()->status);
        Storage::disk('s3')->assertMissing($session->object_key);

        // The command is safe to run repeatedly.
        $this->artisan('albuvo:cleanup-uploads')->assertSuccessful();
        $this->assertSame(0, $album->fresh()->reserved_bytes);
    }
}
