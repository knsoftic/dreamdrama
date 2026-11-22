<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\Setting;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class NotificationController extends Controller
{
    public function __construct(private FcmService $fcm) {}

    public function index()
    {
        return view('admin.notifications', [
            'configured' => $this->fcm->isConfigured(),
            'projectId' => $this->fcm->projectId(),
            'devices' => DeviceToken::query()->count(),
            'users' => DeviceToken::query()->distinct('user_id')->count('user_id'),
            'hasAccount' => (string) Setting::get('fcm_service_account', '') !== '',
        ]);
    }

    public function saveConfig(Request $request)
    {
        $data = $request->validate(['fcm_service_account' => ['nullable', 'string', 'max:20000']]);
        $json = trim((string) $data['fcm_service_account']);

        if ($json !== '') {
            $parsed = json_decode($json, true);
            if (! isset($parsed['client_email'], $parsed['private_key'], $parsed['project_id'], $parsed['token_uri'])) {
                throw ValidationException::withMessages([
                    'fcm_service_account' => 'This does not look like a valid Firebase service-account JSON (client_email / private_key / project_id missing).',
                ]);
            }
        }

        Setting::put(['fcm_service_account' => $json]);

        return back()->with('success', $json === '' ? 'Firebase config removed.' : 'Firebase config saved.');
    }

    public function send(Request $request)
    {
        if (! $this->fcm->isConfigured()) {
            return back()->withErrors(['title' => 'Save the Firebase service account first.']);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'url' => ['nullable', 'url', 'max:255'],
            'audience' => ['required', 'in:all,active'],
        ]);

        $query = DeviceToken::query();
        if ($data['audience'] === 'active') {
            $query->whereHas('user', fn ($q) => $q->where('status', 'active')->where('is_flagged', false));
        }

        $tokens = $query->pluck('token');
        if ($tokens->isEmpty()) {
            return back()->withErrors(['title' => 'No device has installed the app / allowed notifications yet.']);
        }

        try {
            $result = $this->fcm->send($tokens, $data['title'], $data['body'], $data['url'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['title' => $e->getMessage()]);
        }

        return back()->with('success', "Notification sent: {$result['sent']} delivered, {$result['failed']} failed"
            .($result['removed'] ? ", {$result['removed']} stale tokens removed" : '').'.');
    }
}
