<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $videos = Video::query()
            ->when($request->query('q'), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->withCount(['watchSessions as earned_views' => fn ($q) => $q->where('counted', true)])
            ->orderByDesc('sort_order')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.videos.index', ['videos' => $videos]);
    }

    public function create()
    {
        return view('admin.videos.form', ['video' => new Video(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $data['file_path'] = $request->file('video')->store('videos', 'public');
        $data['thumbnail_path'] = $this->storeThumbnail($request);
        $data['uploaded_by'] = $request->user()->id;

        Video::query()->create($data);

        return $this->done($request, 'Video upload ho gayi.');
    }

    /** The upload form posts with JavaScript (for the progress bar), so answer JSON there. */
    private function done(Request $request, string $message)
    {
        session()->flash('success', $message);

        return $request->wantsJson()
            ? response()->json(['redirect' => route('admin.videos.index')])
            : redirect()->route('admin.videos.index');
    }

    public function edit(Video $video)
    {
        return view('admin.videos.form', ['video' => $video]);
    }

    public function update(Request $request, Video $video)
    {
        $data = $this->validated($request, false);

        if ($request->hasFile('video')) {
            Storage::disk('public')->delete($video->file_path);
            $data['file_path'] = $request->file('video')->store('videos', 'public');
        }
        if ($thumb = $this->storeThumbnail($request)) {
            if ($video->thumbnail_path) {
                Storage::disk('public')->delete($video->thumbnail_path);
            }
            $data['thumbnail_path'] = $thumb;
        }
        if (! $request->hasFile('video')) {
            unset($data['duration_seconds']);
            if ($request->filled('duration_seconds')) {
                $data['duration_seconds'] = (int) $request->input('duration_seconds');
            }
        }

        $video->update($data);

        return $this->done($request, 'Video update ho gayi.');
    }

    public function toggle(Video $video)
    {
        $video->update(['is_active' => ! $video->is_active]);

        return back()->with('success', $video->is_active ? 'Video active ho gayi.' : 'Video hide ho gayi.');
    }

    public function destroy(Video $video)
    {
        Storage::disk('public')->delete(array_filter([$video->file_path, $video->thumbnail_path]));
        $video->delete();

        return redirect()->route('admin.videos.index')->with('success', 'Video delete ho gayi.');
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:60'],
            'video' => [$creating ? 'required' : 'nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime,video/x-matroska', 'max:204800'],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
            'thumbnail_data' => ['nullable', 'string'],
            'duration_seconds' => [$creating ? 'required' : 'nullable', 'integer', 'min:1', 'max:3600'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'duration_seconds.required' => 'Video ki duration detect nahi hui. Video dobara select karein ya duration khud likhein.',
        ]);

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? null,
            'duration_seconds' => (int) ($data['duration_seconds'] ?? 0),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** Accepts an uploaded image or a thumbnail captured in the browser (data URL). */
    private function storeThumbnail(Request $request): ?string
    {
        if ($request->hasFile('thumbnail')) {
            return $request->file('thumbnail')->store('thumbnails', 'public');
        }

        $dataUrl = (string) $request->input('thumbnail_data');
        if (preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#', $dataUrl, $m)) {
            $binary = base64_decode($m[2], true);
            $isImage = $binary !== false && (function_exists('imagecreatefromstring')
                ? (bool) @imagecreatefromstring($binary)
                : (str_starts_with($binary, "\xFF\xD8\xFF") || str_starts_with($binary, "\x89PNG") || substr($binary, 8, 4) === 'WEBP'));

            if ($isImage && strlen($binary) < 3 * 1024 * 1024) {
                $path = 'thumbnails/'.Str::random(32).'.'.($m[1] === 'jpeg' ? 'jpg' : $m[1]);
                Storage::disk('public')->put($path, $binary);

                return $path;
            }
        }

        return null;
    }
}
