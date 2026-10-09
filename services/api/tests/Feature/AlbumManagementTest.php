<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\AlbumMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AlbumManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seedAlbum(): array
    {
        $owner=User::factory()->create();
        $member=User::factory()->create();
        $outsider=User::factory()->create();
        $album=Album::create(['owner_id'=>$owner->id,
            'slug'=>'manage-'.Str::random(12),'title'=>'Original',
            'allow_guest_upload'=>true,'require_upload_approval'=>true]);
        $ownerMember=AlbumMember::create(['album_id'=>$album->id,'user_id'=>$owner->id,'role'=>'owner']);
        $viewerMember=AlbumMember::create(['album_id'=>$album->id,'user_id'=>$member->id,'role'=>'viewer']);
        return [$owner,$member,$outsider,$album,$ownerMember,$viewerMember];
    }

    public function test_owner_can_edit_album_but_member_cannot(): void
    {
        [$owner,$member,$outsider,$album]=$this->seedAlbum();
        $url="/api/v1/albums/{$album->id}";
        $changes=['title'=>'Nuevo título','description'=>'Nueva descripción',
            'allow_guest_upload'=>false,'require_upload_approval'=>false];
        $this->actingAs($member)->patchJson($url,$changes)->assertForbidden();
        $this->actingAs($outsider)->patchJson($url,$changes)->assertNotFound();
        $this->actingAs($owner)->patchJson($url,$changes)
            ->assertOk()->assertJsonPath('album.title','Nuevo título')
            ->assertJsonPath('album.allow_guest_upload',false)
            ->assertJsonPath('album.require_upload_approval',false);
        $this->assertSame('Nuevo título',$album->fresh()->title);
        $this->assertSame('Nueva descripción',$album->fresh()->description);
    }

    public function test_owner_can_remove_member_but_not_self(): void
    {
        [$owner,$viewer,$outsider,$album,$ownerMembership,$viewerMembership]=$this->seedAlbum();
        $url="/api/v1/albums/{$album->id}/members";
        $this->actingAs($outsider)->getJson($url)->assertNotFound();
        $this->actingAs($viewer)->getJson($url)->assertForbidden();
        $this->actingAs($owner)->getJson($url)->assertOk()->assertJsonCount(2,'members');
        $this->actingAs($owner)->deleteJson($url.'/'.$ownerMembership->id)->assertForbidden();
        $this->actingAs($viewer)->deleteJson($url.'/'.$viewerMembership->id)->assertForbidden();
        $this->actingAs($owner)->deleteJson($url.'/'.$viewerMembership->id)->assertNoContent();
        $this->assertSame('removed',$viewerMembership->fresh()->state);
        $this->actingAs($viewer)->getJson("/api/v1/albums/{$album->id}")->assertNotFound();
    }
}
