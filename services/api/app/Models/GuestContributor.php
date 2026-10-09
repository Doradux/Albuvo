<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class GuestContributor extends Model {
    use HasUuids;
    protected $guarded=[];
    public function invite() { return $this->belongsTo(AlbumInvite::class,'invite_id'); }
}