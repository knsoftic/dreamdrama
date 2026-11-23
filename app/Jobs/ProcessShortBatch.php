<?php

namespace App\Jobs;

use App\Models\ShortBatch;
use App\Models\Video;
use App\Services\VideoShortService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProcessShortBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public int $batchId) {}

    public function handle(VideoShortService $svc): void
    {
        $batch = ShortBatch::find($this->batchId);
        if (! $batch || $batch->status === 'done') {
            return;
        }

        $batch->update(['status' => 'processing', 'error' => null]);

        if (! $svc->available()) {
            $batch->update(['status' => 'failed', 'error' => 'ffmpeg not found on the server. Install ffmpeg or set FFMPEG_PATH in .env.']);

            return;
        }

        $srcAbs = $batch->source_path ? Storage::disk('local')->path($batch->source_path) : null;
        if (! $srcAbs || ! is_file($srcAbs)) {
            $batch->update(['status' => 'failed', 'error' => 'Source video file is missing.']);

            return;
        }

        $opt = $batch->options ?? [];
        $windows = $svc->windows($svc->duration($srcAbs), $opt);
        $batch->update(['total' => count($windows), 'done' => 0]);

        if (! $windows) {
            $batch->update(['status' => 'failed', 'error' => 'Video is too short to make any clips.']);
            Storage::disk('local')->delete($batch->source_path);

            return;
        }

        $reframe = $opt['reframe'] ?? 'blur';
        $pub = Storage::disk('public');
        $made = 0;

        foreach ($windows as $i => [$start, $len]) {
            $name = 'videos/short_'.Str::random(24).'.mp4';
            $thumb = 'thumbnails/short_'.Str::random(24).'.jpg';
            $outAbs = $pub->path($name);
            $thAbs = $pub->path($thumb);
            @mkdir(dirname($outAbs), 0775, true);
            @mkdir(dirname($thAbs), 0775, true);

            if (! $svc->cut($srcAbs, $start, $len, $outAbs, $reframe)) {
                continue;
            }
            $hasThumb = $svc->thumb($srcAbs, $start + min(1.0, $len / 2), $thAbs);

            Video::query()->create([
                'title' => Str::limit(trim(($opt['title_prefix'] ?? $batch->source_title).' — Short '.($i + 1)), 150, ''),
                'category' => $opt['category'] ?? null,
                'file_path' => $name,
                'thumbnail_path' => $hasThumb ? $thumb : null,
                'duration_seconds' => (int) round($len),
                'is_active' => false,                 // draft — admin bulk-publishes after review
                'sort_order' => 0,
                'uploaded_by' => $batch->created_by,
                'short_batch_id' => $batch->id,
            ]);

            $batch->increment('done');
            $made++;
        }

        Storage::disk('local')->delete($batch->source_path);

        $batch->update([
            'status' => $made > 0 ? 'done' : 'failed',
            'source_path' => null,
            'error' => $made > 0 ? null : 'ffmpeg could not cut any clips. Check the source file and that ffmpeg has the libx264 encoder.',
        ]);
    }

    public function failed(\Throwable $e): void
    {
        ShortBatch::whereKey($this->batchId)->update([
            'status' => 'failed',
            'error' => Str::limit($e->getMessage(), 500),
        ]);
    }
}
