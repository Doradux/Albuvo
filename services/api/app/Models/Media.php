<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class Media extends Model {
    use HasUuids;
    protected $table='media';
    protected $guarded=[];
    protected function casts(): array { return ['published_at'=>'datetime']; }
    public function album() { return $this->belongsTo(Album::class); }
    public function objects() { return $this->hasMany(MediaObject::class); }
}