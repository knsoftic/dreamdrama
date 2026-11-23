<?php

namespace App\Console\Commands;

use App\Jobs\ProcessShortBatch;
use App\Models\ShortBatch;
use App\Services\VideoShortService;
use Illuminate\Console\Command;

class ProcessShortsCommand extends Command
{
    protected $signature = 'shorts:process {batch? : Batch id (default: the next queued batch)}';

    protected $description = 'Cut an uploaded video into shorts inline. Use this on servers without a queue worker (e.g. from cron).';

    public function handle(VideoShortService $svc): int
    {
        if (! $svc->available()) {
            $this->error('ffmpeg not found. Install ffmpeg or set FFMPEG_PATH in .env.');

            return self::FAILURE;
        }

        $batch = $this->argument('batch')
            ? ShortBatch::find($this->argument('batch'))
            : ShortBatch::where('status', 'queued')->orderBy('id')->first();

        if (! $batch) {
            $this->info('No batch to process.');

            return self::SUCCESS;
        }

        $this->info("Processing batch #{$batch->id}: {$batch->source_title}");
        (new ProcessShortBatch($batch->id))->handle($svc);
        $fresh = $batch->fresh();
        $this->info("Status: {$fresh->status} — {$fresh->done} short(s) created.".($fresh->error ? " ({$fresh->error})" : ''));

        return self::SUCCESS;
    }
}
