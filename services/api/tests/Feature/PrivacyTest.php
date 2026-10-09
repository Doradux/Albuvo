<?php
namespace Tests\Feature;

use App\Models\Album;
use App\Models\AlbumInvite;
use App\Models\AlbumMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PrivacyTest extends TestCase {
    use RefreshDatabase;

    private function makeAlbum(User $owner): Album {
        $album=Album::create(['owner_id'=>$owner->id,'slug'=>'test-'.Str::random(12),'title'=>'Recuerdos','allow_guest_upload'=>true]);
        AlbumMember::create(['album_id'=>$album->id,'user_id'=>$owner->id,'role'=>'owner']);
        return $album;
    }

    public function test_other_users_cannot_see_private_albums_or_media(): void {
        $owner=User::factory()->create();
        $outsider=User::factory()->create();
        $album=$this->makeAlbum($owner);
        $this->actingAs($outsider)->getJson('/api/v1/albums/'.$album->id)->assertNotFound();
        $this->actingAs($outsider)->getJson('/api/v1/albums/'.$album->id.'/media')->assertNotFound();
        $this->actingAs($outsider)->getJson('/api/v1/albums/'.$album->id.'/moderation')->assertNotFound();
        $this->actingAs($owner)->getJson('/api/v1/albums/'.$album->id)->assertOk()->assertJsonPath('role','owner');
    }
    public function test_viewers_cannot_moderate_or_create_invites(): void {
        $owner=User::factory()->create();
        $viewer=User::factory()->create();
        $album=$this->makeAlbum($owner);
        AlbumMember::create(['album_id'=>$album->id,'user_id'=>$viewer->id,'role'=>'viewer']);
        $this->actingAs($viewer)->getJson('/api/v1/albums/'.$album->id.'/moderation')->assertForbidden();
        $this->actingAs($viewer)->postJson('/api/v1/albums/'.$album->id.'/invites',[])->assertForbidden();
    }
    public function test_revoked_guest_invitation_cannot_be_accepted(): void {
        $owner=User::factory()->create();
        $album=$this->makeAlbum($owner);
        $token=Str::random(64);
        AlbumInvite::create(['album_id'=>$album->id,'created_by'=>$owner->id,'token_hash'=>hash('sha256',$token),
            'scope'=>'upload','expires_at'=>now()->addDay(),'revoked_at'=>now()]);
        $this->postJson('/api/v1/album-invites/resolve',
            ['token'=>$token,'display_name'=>'Persona invitada','accept_rules'=>true])->assertNotFound();
    }
}
