<?php
namespace App\Support;

use App\Models\Album;
use App\Models\AlbumMember;
use App\Models\GuestContributor;
use Illuminate\Http\Request;

trait AlbumAccess {
    private function access(Request $r, Album $album): array {
        if ($r->user()) {
            $member=AlbumMember::where('album_id',$album->id)->where('user_id',$r->user()->id)
                ->where('state','active')->first();
            if ($member) return ['role'=>$member->role,'user_id'=>$r->user()->id,'guest_id'=>null];
        }
        $secret=$r->header('X-Guest-Token');
        if ($secret && strlen($secret)>=40) {
            $guest=GuestContributor::where('album_id',$album->id)->where('secret_hash',hash('sha256',$secret))
                ->with('invite')->first();
            if ($guest && $guest->invite && $guest->invite->isActive()) {
                return ['role'=>'guest','user_id'=>null,'guest_id'=>$guest->id,'invite'=>$guest->invite];
            }
        }
        abort(404, 'Álbum no encontrado.');
    }
    private function canModerate(array $actor): bool {
        return in_array($actor['role'],['owner','admin','moderator'],true);
    }
    private function canUpload(array $actor, Album $album): bool {
        if ($album->status!=='active') return false;
        if ($actor['role']==='guest') return $album->allow_guest_upload && $actor['invite']->scope==='upload';
        return in_array($actor['role'],['owner','admin','moderator','contributor'],true);
    }
}