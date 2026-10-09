<?php
namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\AlbumInvite;
use App\Models\AlbumMember;
use App\Models\GuestContributor;
use App\Models\User;
use App\Support\AlbumAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AlbumController extends Controller {
    use AlbumAccess;
    public function index(Request $r) {
        abort_unless($r->user(),401);
        $ids=AlbumMember::where('user_id',$r->user()->id)->where('state','active')->pluck('album_id');
        return ['albums'=>Album::whereIn('id',$ids)->withCount(['media'=>fn($q)=>$q->where('status','approved')])
            ->latest()->get(['id','title','description','slug','status','visibility','quota_bytes','used_bytes','created_at'])];
    }
    public function store(Request $r) {
        abort_unless($r->user()?->hasVerifiedEmail(),403,'Verifica tu correo electrónico para crear álbumes.');
        $v=$r->validate([
            'title'=>'required|string|min:1|max:100','description'=>'nullable|string|max:500',
            'allow_guest_upload'=>'boolean','require_upload_approval'=>'boolean',
        ]);
        $album=DB::transaction(function() use ($r,$v) {
            $album=Album::create([
                'owner_id'=>$r->user()->id,'slug'=>Str::slug($v['title']).'-'.Str::lower(Str::random(8)),
                'title'=>$v['title'],'description'=>$v['description']??null,'visibility'=>'private',
                'allow_guest_upload'=>$v['allow_guest_upload']??true,
                'require_upload_approval'=>$v['require_upload_approval']??true,
            ]);
            AlbumMember::create(['album_id'=>$album->id,'user_id'=>$r->user()->id,'role'=>'owner']);
            return $album;
        });
        return response()->json(['album'=>$album],201);
    }
    public function show(Request $r, Album $album) {
        $actor=$this->access($r,$album);
        return ['album'=>$album->only(['id','title','description','slug','status','visibility','allow_guest_upload',
                'allow_member_download','require_upload_approval','quota_bytes','used_bytes','reserved_bytes','created_at']),
            'role'=>$actor['role'], 'can_upload'=>$this->canUpload($actor,$album),
            'can_moderate'=>$this->canModerate($actor)];
    }
    public function previewInvite(Request $r) {
        $v=$r->validate(['token'=>'required|string|min:40|max:120']);
        $invite=AlbumInvite::where('token_hash',hash('sha256',$v['token']))->with('album')->first();
        abort_unless($invite && $invite->isActive() && $invite->album->status==='active',404,'Esta invitación no es válida.');
        return ['album'=>['id'=>$invite->album->id,'title'=>$invite->album->title],
            'scope'=>$invite->scope,'expires_at'=>$invite->expires_at];
    }
    public function acceptInvite(Request $r) {
        $v=$r->validate(['token'=>'required|string|min:40|max:120','display_name'=>'required|string|min:2|max:80','accept_rules'=>'accepted']);
        $result=DB::transaction(function() use ($v) {
            $invite=AlbumInvite::where('token_hash',hash('sha256',$v['token']))->lockForUpdate()->first();
            abort_unless($invite && $invite->isActive() && $invite->album->status==='active',404,'Invitación caducada o revocada.');
            $secret=Str::random(64);
            $guest=GuestContributor::create([
                'album_id'=>$invite->album_id,'invite_id'=>$invite->id,'display_name'=>$v['display_name'],
                'secret_hash'=>hash('sha256',$secret),'consent_version'=>'draft-2026-10',
            ]);
            DB::table('consent_acceptances')->insert([
                'id'=>(string)Str::uuid(),'guest_contributor_id'=>$guest->id,'document_type'=>'community_rules',
                'version'=>'draft-2026-10','accepted_at'=>now(),
            ]);
            $invite->increment('used_count');
            return ['guest_token'=>$secret,'album_id'=>$invite->album_id,'display_name'=>$guest->display_name];
        });
        return $result;
    }
    public function invites(Request $r, Album $album) {
        $actor=$this->access($r,$album);
        abort_unless(in_array($actor['role'],['owner','admin']),403);
        return ['invites'=>$album->invites()->latest()->get(['id','scope','used_count','max_uses','expires_at','revoked_at','created_at'])];
    }
    public function createInvite(Request $r, Album $album) {
        $actor=$this->access($r,$album);
        abort_unless(in_array($actor['role'],['owner','admin']),403);
        $v=$r->validate(['scope'=>'sometimes|in:view,upload','hours'=>'sometimes|integer|min:1|max:720','max_uses'=>'nullable|integer|min:1|max:10000']);
        $secret=Str::random(64);
        $invite=AlbumInvite::create([
            'album_id'=>$album->id,'created_by'=>$actor['user_id'],'token_hash'=>hash('sha256',$secret),
            'scope'=>$v['scope']??'upload','max_uses'=>$v['max_uses']??null,
            'expires_at'=>now()->addHours($v['hours']??168),
        ]);
        return response()->json(['invite'=>$invite->only(['id','scope','expires_at','max_uses']),
            'token'=>$secret,'share_url'=>rtrim(env('FRONTEND_URL','http://localhost:5174'),'/').'/invite/'.$secret],201);
    }
    public function revokeInvite(Request $r, Album $album, AlbumInvite $invite) {
        $actor=$this->access($r,$album);
        abort_unless(in_array($actor['role'],['owner','admin']),403);
        abort_unless($invite->album_id===$album->id,404);
        $invite->update(['revoked_at'=>now()]);
        return response()->noContent();
    }
    public function addMember(Request $r, Album $album) {
        $actor=$this->access($r,$album);
        abort_unless(in_array($actor['role'],['owner','admin']),403);
        $v=$r->validate(['email'=>'required|email','role'=>'required|in:viewer,contributor,moderator']);
        $user=User::where('email',mb_strtolower($v['email']))->first();
        abort_unless($user,422,'La persona debe tener una cuenta para recibir este permiso.');
        $member=AlbumMember::updateOrCreate(['album_id'=>$album->id,'user_id'=>$user->id],
            ['role'=>$v['role'],'state'=>'active']);
        return response()->json(['member'=>$member],201);
    }
}