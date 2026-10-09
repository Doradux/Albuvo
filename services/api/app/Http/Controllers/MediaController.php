<?php
namespace App\Http\Controllers;
use App\Jobs\ProcessImage;
use App\Models\Album;
use App\Models\Media;
use App\Models\UploadSession;
use App\Support\AlbumAccess;
use App\Support\PrivateMediaUrls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller {
    use AlbumAccess;
    private function serializeMedia(Media $m): array {
        $preview=$m->objects->firstWhere('kind','preview');
        $thumb=$m->objects->firstWhere('kind','thumbnail');
        return ['id'=>$m->id,'status'=>$m->status,'created_at'=>$m->created_at,
            'width'=>$m->width,'height'=>$m->height,'reason'=>$m->moderation_reason,
            'preview_url'=>$preview?PrivateMediaUrls::signedUrl('GetObject',$preview->object_key):null,
            'thumbnail_url'=>$thumb?PrivateMediaUrls::signedUrl('GetObject',$thumb->object_key):null];
    }
    public function index(Request $r, Album $album) {
        $actor=$this->access($r,$album);
        $q=Media::where('album_id',$album->id)->where(function($q) use($actor) {
            $q->where('status','approved');
            if($actor['user_id']) $q->orWhere(fn($x)=>$x->where('user_id',$actor['user_id'])->whereIn('status',['pending','processing','failed']));
            if($actor['guest_id']) $q->orWhere(fn($x)=>$x->where('guest_contributor_id',$actor['guest_id'])->whereIn('status',['pending','processing','failed']));
        });
        return ['media'=>$q->with('objects')->latest()->limit(100)->get()->map(fn($m)=>$this->serializeMedia($m))];
    }
    public function moderation(Request $r, Album $album) {
        $actor=$this->access($r,$album); abort_unless($this->canModerate($actor),403);
        return ['media'=>Media::where('album_id',$album->id)->where('status','pending')
            ->with('objects')->oldest()->limit(100)->get()->map(fn($m)=>$this->serializeMedia($m))];
    }
    public function init(Request $r, Album $album) {
        $actor=$this->access($r,$album); abort_unless($this->canUpload($actor,$album),403);
        $v=$r->validate([
            'filename'=>'required|string|max:255',
            'mime'=>'required|in:image/jpeg',
            'size'=>'required|integer|min:100|max:12582912',
            'idempotency_key'=>'required|uuid',
        ]);
        $session=DB::transaction(function() use($album,$actor,$v) {
            $a=Album::whereKey($album->id)->lockForUpdate()->firstOrFail();
            // A retry of the SAME upload must not reserve the quota twice.
            $existing=UploadSession::where('album_id',$a->id)->where('idempotency_key',$v['idempotency_key'])->first();
            if($existing) {
                abort_unless($existing->user_id===$actor['user_id'] &&
                    $existing->guest_contributor_id===$actor['guest_id'] &&
                    $existing->declared_size===$v['size'] && $existing->declared_mime===$v['mime'] &&
                    $existing->status==='initiated' && $existing->expires_at->isFuture(),409,
                    'Esta subida ya se utilizó o ha caducado.');
                return $existing;
            }
            abort_if($a->used_bytes+$a->reserved_bytes+$v['size']>$a->quota_bytes,409,'Sin espacio.');
            $m=Media::create(['album_id'=>$a->id,'user_id'=>$actor['user_id'],
                'guest_contributor_id'=>$actor['guest_id'],'original_filename'=>basename($v['filename'])]);
            $s=UploadSession::create(['album_id'=>$a->id,'media_id'=>$m->id,'user_id'=>$actor['user_id'],
                'guest_contributor_id'=>$actor['guest_id'],'idempotency_key'=>$v['idempotency_key'],
                'object_key'=>'ingest/'.Str::random(36).'/'.$m->id.'.jpg','declared_size'=>$v['size'],
                'reserved_bytes'=>$v['size'],'declared_mime'=>$v['mime'],'expires_at'=>now()->addHours(2)]);
            $a->increment('reserved_bytes',$v['size']);
            return $s;
        });
        return response()->json(['upload_id'=>$session->id,
            'put_url'=>PrivateMediaUrls::signedUrl('PutObject',$session->object_key,['ContentType'=>'image/jpeg']),
            'expires_in_seconds'=>600],201);
    }
    public function complete(Request $r, UploadSession $session) {
        $album=Album::findOrFail($session->album_id);
        $actor=$this->access($r,$album);
        abort_unless($session->user_id===$actor['user_id'] && $session->guest_contributor_id===$actor['guest_id'],404);
        if($session->status==='completed' || $session->status==='processing') return ['status'=>$session->status];
        abort_unless($session->status==='initiated' && $session->expires_at->isFuture(),409,'Subida caducada.');
        $disk=Storage::disk('s3');
        abort_unless($disk->exists($session->object_key),409,'No se recibió la imagen.');
        abort_unless($disk->size($session->object_key)===$session->declared_size,422,'Tamaño incorrecto.');
        DB::transaction(function() use($session) {
            $s=UploadSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if($s->status!=='initiated') return;
            $s->update(['status'=>'processing']);
            Media::whereKey($s->media_id)->update(['status'=>'processing']);
        });
        ProcessImage::dispatch($session->media_id);
        return response()->json(['status'=>'processing'],202);
    }
    public function uploadStatus(Request $r, UploadSession $session) {
        $actor=$this->access($r,Album::findOrFail($session->album_id));
        abort_unless($session->user_id===$actor['user_id'] && $session->guest_contributor_id===$actor['guest_id'],404);
        return ['status'=>$session->fresh()->status,'media_status'=>Media::find($session->media_id)?->status];
    }
    private function decide(Request $r, Media $media, string $decision) {
        $actor=$this->access($r,$media->album);
        abort_unless($this->canModerate($actor),403);
        $v=$r->validate(['reason'=>'nullable|string|max:500']);
        DB::transaction(function() use($media,$actor,$decision,$v) {
            $m=Media::whereKey($media->id)->lockForUpdate()->firstOrFail();
            abort_unless($m->status==='pending',409,'La imagen ya ha sido revisada.');
            $m->update(['status'=>$decision==='approve'?'approved':'rejected',
                'moderated_by'=>$actor['user_id'],'moderation_reason'=>$v['reason']??null,
                'published_at'=>$decision==='approve'?now():null]);
            DB::table('moderation_actions')->insert(['id'=>(string)Str::uuid(),'media_id'=>$m->id,
                'actor_id'=>$actor['user_id'],'decision'=>$decision,'reason'=>$v['reason']??null,
                'created_at'=>now(),'updated_at'=>now()]);
        });
        return ['status'=>$decision==='approve'?'approved':'rejected'];
    }
    public function approve(Request $r, Media $media) { return $this->decide($r,$media,'approve'); }
    public function reject(Request $r, Media $media) { return $this->decide($r,$media,'reject'); }
    public function report(Request $r) {
        $v=$r->validate(['album_id'=>'required|uuid','media_id'=>'nullable|uuid',
            'reason'=>'required|in:privacy,copyright,harassment,abuse,other',
            'description'=>'nullable|string|max:1000','contact_email'=>'nullable|email|max:255']);
        $album=Album::findOrFail($v['album_id']); $actor=$this->access($r,$album);
        if(!empty($v['media_id'])) {
            $m=Media::findOrFail($v['media_id']);
            abort_unless($m->album_id===$album->id && ($m->status==='approved' || $this->canModerate($actor)
                || ($actor['user_id'] && $m->user_id===$actor['user_id'])
                || ($actor['guest_id'] && $m->guest_contributor_id===$actor['guest_id'])),404);
        }
        DB::table('reports')->insert(['id'=>(string)Str::uuid(),'album_id'=>$album->id,
            'media_id'=>$v['media_id']??null,'user_id'=>$actor['user_id'],
            'guest_contributor_id'=>$actor['guest_id'],'reason'=>$v['reason'],
            'description'=>$v['description']??null,'contact_email'=>$v['contact_email']??null,
            'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['message'=>'Denuncia recibida para revisión.'],201);
    }
}
