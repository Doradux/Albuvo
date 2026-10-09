<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class AlbumInvite extends Model {
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['expires_at'=>'datetime','revoked_at'=>'datetime']; }
    public function album() { return $this->belongsTo(Album::class); }
    public function isActive(): bool {
        return !$this->revoked_at && $this->expires_at->isFuture()
            && ($this->max_uses===null || $this->used_count<$this->max_uses);
    }
}