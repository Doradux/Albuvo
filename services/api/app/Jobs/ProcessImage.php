<?php
namespace App\Jobs;
use App\Models\Album;
use App\Models\Media;
use App\Models\MediaObject;
use App\Models\UploadSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessImage implements ShouldQueue {
    use Queueable;
    public int $tries = 1;
    public function __construct(public string $mediaId) {}
    private function jpeg($source,int $limit,int $quality): array {
        $w=imagesx($source); $h=imagesy($source);
        $scale=min(1,$limit/max($w,$h));
        $nw=max(1,(int)($w*$scale)); $nh=max(1,(int)($h*$scale));
        $canvas=imagecreatetruecolor($nw,$nh);
        imagecopyresampled($canvas,$source,0,0,0,0,$nw,$nh,$w,$h);
        ob_start(); imagejpeg($canvas,null,$quality); $bytes=ob_get_clean();
        imagedestroy($canvas);
        return [$bytes,$nw,$nh];
    }
    public function handle(): void {
        $session=UploadSession::where('media_id',$this->mediaId)->firstOrFail();
        if($session->status!=='processing') return;
        $disk=Storage::disk('s3');
        try {
            $binary=$disk->get($session->object_key);
            if(!$binary || strlen($binary)>12582912) throw new \RuntimeException('JPEG demasiado grande');
            $info=@getimagesizefromstring($binary);
            if(!$info || $info[2]!==IMAGETYPE_JPEG || $info[0]*$info[1]>32000000)
                throw new \RuntimeException('Formato o resolución no soportada');
            $source=@imagecreatefromstring($binary);
            if(!$source) throw new \RuntimeException('JPEG corrupto');
            [$preview,$pw,$ph]=$this->jpeg($source,1280,83);
            [$thumb,$tw,$th]=$this->jpeg($source,420,78);
            imagedestroy($source);
            $base='private/'.Str::random(24).'/'.$this->mediaId.'/';
            $disk->put($base.'preview.jpg',$preview,['visibility'=>'private','ContentType'=>'image/jpeg']);
            $disk->put($base.'thumb.jpg',$thumb,['visibility'=>'private','ContentType'=>'image/jpeg']);
            DB::transaction(function() use($session,$info,$base,$preview,$thumb,$pw,$ph,$tw,$th) {
                $album=Album::whereKey($session->album_id)->lockForUpdate()->firstOrFail();
                $media=Media::whereKey($session->media_id)->lockForUpdate()->firstOrFail();
                if($media->status!=='processing') return;
                foreach ([['preview',$preview,$pw,$ph],['thumbnail',$thumb,$tw,$th]] as [$kind,$data,$w,$h]) {
                    MediaObject::create(['media_id'=>$media->id,'kind'=>$kind,
                      'object_key'=>$base.($kind==='preview'?'preview.jpg':'thumb.jpg'),
                      'mime'=>'image/jpeg','byte_size'=>strlen($data),'width'=>$w,'height'=>$h]);
                }
                $actual=strlen($preview)+strlen($thumb);
                $album->reserved_bytes=max(0,$album->reserved_bytes-$session->reserved_bytes);
                $album->used_bytes+=$actual; $album->save();
                $media->update(['mime_detected'=>'image/jpeg','width'=>$info[0],'height'=>$info[1],
                  'byte_size'=>$actual,'status'=>$album->require_upload_approval?'pending':'approved',
                  'published_at'=>$album->require_upload_approval?null:now()]);
                $session->update(['status'=>'completed','finalized_at'=>now()]);
            });
            $disk->delete($session->object_key);
        } catch (\Throwable $e) {
            DB::transaction(function() use($session) {
                $album=Album::whereKey($session->album_id)->lockForUpdate()->first();
                if($album) { $album->reserved_bytes=max(0,$album->reserved_bytes-$session->reserved_bytes); $album->save(); }
                Media::whereKey($session->media_id)->update(['status'=>'failed']);
                $session->update(['status'=>'failed']);
            });
            $disk->delete($session->object_key);
            throw $e;
        }
    }
}
