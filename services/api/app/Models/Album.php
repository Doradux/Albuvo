<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Album extends Model {
    use HasUuids;
    protected $guarded = [];
    protected function casts(): array { return ['allow_guest_upload'=>'boolean','require_upload_approval'=>'boolean','allow_member_download'=>'boolean']; }
    public function owner() { return $this->belongsTo(User::class,'owner_id'); }
    public function members() { return $this->hasMany(AlbumMember::class); }
    public function invites() { return $this->hasMany(AlbumInvite::class); }
    public function media() { return $this->hasMany(Media::class); }
}