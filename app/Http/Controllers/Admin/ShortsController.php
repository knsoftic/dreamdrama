<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessShortBatch;
use App\Models\ShortBatch;
use App\Services\VideoShortService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ShortsController extends Controller
{
    public function index()
    {
        return view('admin.shorts.index', [
            'batches' => ShortBatch::query()->withCount('videos')->latest('id')->paginate(15),
            'ready' => app(VideoShortService::class)->available(),
        ]);
    }

    public function create()
    {
        return view('admin.shorts.create', [
            'ready' => app(VideoShortService::class)->available(),
        ]);
    }

    public function store(Request $request)
    {
        $maxKb = (int) config('media.source_max_kb', 1048576);

        $data = $request->validate([
            'source_title' => ['required', 'string', 'max:150'],
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime,video/x-matroska,video/x-msvideo', 'max:'.$maxKb],
            'clip_length' => ['required', 'integer', 'min:5', 'max:180'],
            'max_shorts' => ['required', 'integer', 'min:1', 'max:50'],
            'skip_intro' => ['nullable', 'integer', 'min:0', 'max:600'],
            'skip_outro' => ['nullable', 'integer', 'min:0', 'max:600'],
            'reframe' => ['required', 'in:blur,crop'],
            'category' => ['nullable', 'string', 'max:60'],
        ], [
            'video.max' => 'The video is too large. Raise SHORTS_SOURCE_MAX_KB and php.ini upload limits.',
        ]);

        try {
            $path = $request->file('video')->store('shorts-src', 'local');
            abort_unless($path, 500, 'Video ko storage me save nahi kar paaye — storage permissions / disk space check karein.');

            $batch = ShortBatch::query()->create([
                'source_title' => $data['source_title'],
                'source_path' => $path,
                'status' => 'queued',
                'created_by' => $request->user()->id,
                'options' => [
                    'clip_length' => (int) $data['clip_length'],
                    'max_shorts' => (int) $data['max_shorts'],
                    'skip_intro' => (int) ($data['skip_intro'] ?? 0),
                    'skip_outro' => (int) ($data['skip_outro'] ?? 0),
                    'reframe' => $data['reframe'],
                    'category' => $data['category'] ?? null,
                    'title_prefix' => $data['source_title'],
                ],
            ]);

            ProcessShortBatch::dispatch($batch->id);
        } catch (\Throwable $e) {
            report($e);
            $msg = 'Upload save nahi hua: '.$e->getMessage();

            // Surface the real reason so the progress bar can show it (admin-only tool).
            return $request->wantsJson()
                ? response()->json(['message' => $msg], 500)
                : back()->withInput()->with('error', $msg);
        }

        session()->flash('success', 'Video upload ho gaya. Shorts ban rahe hain — processing shuru.');

        // The form uploads with JavaScript (for the progress bar), so answer JSON there.
        return $request->wantsJson()
            ? response()->json(['redirect' => route('admin.shorts.show', $batch)])
            : redirect()->route('admin.shorts.show', $batch);
    }

    public function show(ShortBatch $batch)
    {
        $batch->load(['videos' => fn ($q) => $q->orderByDesc('is_active')->latest('id')]);

        return view('admin.shorts.show', ['batch' => $batch]);
    }

    public function publish(Request $request, ShortBatch $batch)
    {
        $query = $batch->videos();

        if ($request->boolean('all')) {
            $count = $query->where('is_active', false)->update(['is_active' => true]);
        } else {
            $ids = array_filter((array) $request->input('ids', []));
            $count = $ids ? $query->whereIn('id', $ids)->update(['is_active' => true]) : 0;
        }

        return back()->with('success', $count
            ? "{$count} short(s) publish ho gaye — ab feed par live hain."
            : 'Koi short select nahi hua.');
    }

    public function destroy(ShortBatch $batch)
    {
        foreach ($batch->videos as $video) {
            Storage::disk('public')->delete(array_filter([$video->file_path, $video->thumbnail_path]));
            $video->delete();
        }
        if ($batch->source_path) {
            Storage::disk('local')->delete($batch->source_path);
        }
        $batch->delete();

        return redirect()->route('admin.shorts.index')->with('success', 'Batch delete ho gaya.');
    }
}
