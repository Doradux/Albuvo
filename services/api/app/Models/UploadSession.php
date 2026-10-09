<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
class UploadSession extends Model {
    use HasUuids;
    protected $guarded=[];
    protected function casts(): array { return ['expires_at'=>'datetime','finalized_at'=>'datetime']; }
}