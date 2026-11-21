<?php

namespace App\Http\Controllers;

use App\Models\AdUnit;
use App\Models\Video;
use App\Services\EarningService;
use Illuminate\Http\Request;

/** JSON endpoints used by the video feed (session authenticated). */
class AppApiController extends Controller
{
    public function __construct(private EarningService $earning) {}

    public function feed(Request $request)
    {
        $user = $request->user();
        $exclude = collect(explode(',', (string) $request->query('exclude')))
            ->map(fn ($id) => (int) $id)->filter()->take(500)->all();

        $videos = Video::query()
            ->active()
            ->when($exclude, fn ($q) => $q->whereNotIn('id', $exclude))
            ->withExists(['watchSessions as earned' => fn ($q) => $q->where('user_id', $user->id)->where('counted', true)])
            ->orderBy('earned')
            ->orderByDesc('sort_order')
            ->inRandomOrder()
            ->limit(8)
            ->get()
            ->map(fn (Video $v) => [
                'id' => $v->id,
                'title' => $v->title,
                'description' => $v->description,
                'category' => $v->category,
                'url' => $v->url(),
                'thumbnail' => $v->thumbnailUrl(),
                'duration' => (int) $v->duration_seconds,
                'views' => (int) $v->views_count,
                'earned' => (bool) $v->earned,
            ]);

        return response()->json([
            'videos' => $videos,
            'state' => $this->earning->state($user),
        ]);
    }

    public function state(Request $request)
    {
        return response()->json($this->earning->state($request->user()));
    }

    public function watchStart(Request $request)
    {
        $data = $request->validate(['video_id' => ['required', 'integer']]);
        $video = Video::query()->active()->findOrFail($data['video_id']);

        return response()->json($this->earning->startWatch($request->user(), $video, $request->ip()));
    }

    public function heartbeat(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'uuid'],
            'playing' => ['required', 'boolean'],
        ]);

        return response()->json($this->earning->heartbeat($request->user(), $data['token'], (bool) $data['playing']));
    }

    public function watchEnd(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'uuid']]);

        return response()->json($this->earning->endWatch($request->user(), $data['token']));
    }

    public function adStart(Request $request)
    {
        return response()->json($this->earning->startAd($request->user(), $request->ip()));
    }

    public function adComplete(Request $request)
    {
        $data = $request->validate(['ad_view_id' => ['required', 'integer']]);

        return response()->json($this->earning->completeAd($request->user(), (int) $data['ad_view_id']));
    }

    /** Ad codes run inside an iframe so third-party scripts can't touch the app page. */
    public function adFrame(AdUnit $adUnit)
    {
        abort_unless($adUnit->is_active, 404);

        return response()
            ->view('ads.frame', ['unit' => $adUnit])
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }
}
