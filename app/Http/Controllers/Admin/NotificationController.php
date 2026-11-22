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
            'hasGoogleServices' => (string) Setting::get('google_services_json', '') !== '',
            'googleServicesUrl' => url('/google-services.json'),
        ]);
    }

    /**
     * Upload google-services.json. The Android build downloads it from
     * /google-services.json so the APK can receive push notifications.
     */
    public function saveGoogleServices(Request $request)
    {
        $json = '';
        if ($request->hasFile('google_services_file')) {
            $request->validate(['google_services_file' => ['file', 'max:64']]);
            $json = (string) file_get_contents($request->file('google_services_file')->getRealPath());
        } else {
            $data = $request->validate(['google_services_json' => ['nullable', 'string', 'max:40000']]);
            $json = trim((string) ($data['google_services_json'] ?? ''));
        }

        if ($json !== '') {
            $parsed = json_decode($json, true);
            if (! isset($parsed['project_info'], $parsed['client']) || ! is_array($parsed['client'])) {
                throw ValidationException::withMessages([
                    'google_services_json' => 'This does not look like a valid google-services.json (project_info / client missing).',
                ]);
            }
            $packages = collect($parsed['client'])->pluck('client_info.android_client_info.package_name')->filter();
            if ($packages->isNotEmpty() && ! $packages->contains('site.dramaearn.app')) {
                throw ValidationException::withMessages([
                    'google_services_json' => 'This google-services.json is for package '.$packages->first().'. The app package must be site.dramaearn.app — add an Android app with that package in Firebase.',
                ]);
            }
        }

        Setting::put(['google_services_json' => $json]);

        return back()->with('success', $json === ''
            ? 'google-services.json removed.'
            : 'google-services.json saved. Rebuild the APK so it is included.');
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
