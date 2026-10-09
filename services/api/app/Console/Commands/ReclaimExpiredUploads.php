<?php

namespace App\Console\Commands;

use App\Models\Album;
use App\Models\Media;
use App\Models\UploadSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReclaimExpiredUploads extends Command
{
    protected $signature = 'albuvo:cleanup-uploads';
    protected $description = 'Release quota reserved by expired uploads and remove abandoned objects.';

    public function handle(): int
    {
        $count = 0;
        // Each batch is bounded; the hourly scheduler will resume next time.
        UploadSession::query()
            ->where('status', 'initiated')
            ->where('expires_at', '<=', now())
            ->orderBy('expires_at')
            ->limit(200)
            ->pluck('id')
            ->each(function (string $id) use (&$count): void {
                $objectKey = DB::transaction(function () use ($id): ?string {
                    $initial = UploadSession::find($id);
                    if (!$initial) return null;
                    // Keep lock ordering consistent with the init upload path.
                    $album = Album::whereKey($initial->album_id)->lockForUpdate()->first();
                    if (!$album) return null;
                    $session = UploadSession::whereKey($id)->lockForUpdate()->first();
                    if (!$session || $session->status !== 'initiated' || $session->expires_at->isFuture()) {
                        return null;
                    }
                    $album->reserved_bytes = max(0, $album->reserved_bytes - $session->reserved_bytes);
                    $album->save();
                    $session->update(['status' => 'expired', 'finalized_at' => now()]);
                    Media::whereKey($session->media_id)->where('status', 'initiated')->update(['status' => 'failed']);
                    return $session->object_key;
                });
                if ($objectKey === null) return;
                $count++;
                try {
                    Storage::disk('s3')->delete($objectKey);
                } catch (\Throwable $e) {
                    // Quota is already released; flag any leftover object for operations.
                    Log::warning('Expired upload could not be removed from storage', ['key' => $objectKey, 'error' => $e->getMessage()]);
                }
            });

        $this->info("Expired uploads reclaimed: {$count}");
        return self::SUCCESS;
    }
}
