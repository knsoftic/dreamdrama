<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppExtrasTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::query()->create(['name' => 'Admin', 'phone' => '03000000000', 'password' => 'secret123']);
        $u->forceFill(['role' => 'admin'])->save();

        return $u;
    }

    private function user(): User
    {
        return User::query()->create(['name' => 'U', 'phone' => '0300'.random_int(1000000, 9999999), 'password' => 'secret123']);
    }

    public function test_download_page_respects_toggle(): void
    {
        config(['platform.apk_source' => 'https://files.example.com/app.apk']);
        Setting::put(['app_download_enabled' => '1', 'app_version' => '2.3']);

        // The page shows our own file route, never the real source URL.
        $html = $this->get(route('download'))->assertOk()->assertSee('2.3')->assertSee('Download APK')->getContent();
        $this->assertStringNotContainsString('files.example.com', $html);
        $this->assertStringContainsString(route('app.file'), $html);

        Setting::put(['app_download_enabled' => '0']);
        $this->get(route('download'))->assertNotFound();
        $this->get(route('app.file'))->assertNotFound();
    }

    public function test_home_routes_by_app_or_web_and_auth(): void
    {
        // Website guest → landing page is shown.
        $this->get('/')->assertOk()->assertSee('Register');

        // Android app guest → straight to login, no landing.
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 13) DramaEarnApp/1.0')
            ->get('/')->assertRedirect(route('login'));

        // Logged-in user → straight into the app (feed), both on web and in the app.
        $user = $this->user();
        $this->actingAs($user)->get('/')->assertRedirect(route('feed'));
        $this->actingAs($user)->withHeader('User-Agent', 'DramaEarnApp/1.0')->get('/')->assertRedirect(route('feed'));
    }

    public function test_apk_route_404_when_no_source(): void
    {
        config(['platform.apk_source' => '']);
        Setting::put(['app_download_enabled' => '1']);
        $this->get(route('app.file'))->assertNotFound();
    }

    public function test_landing_uses_editable_settings(): void
    {
        Setting::put(['hero_title' => 'My Custom Heading', 'hero_badge' => 'Badge Text', 'brand_color' => '#123456']);
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('My Custom Heading', $html);
        $this->assertStringContainsString('#123456', $html);
    }

    public function test_app_registers_push_token(): void
    {
        $token = str_repeat('a', 40);
        $this->postJson(route('app.push-token'), ['token' => $token])->assertStatus(401);   // guests cannot

        $user = $this->user();
        $this->actingAs($user)->postJson(route('app.push-token'), ['token' => $token])->assertOk();
        $this->assertDatabaseHas('device_tokens', ['user_id' => $user->id, 'token' => $token, 'platform' => 'android']);

        // Same token updates, does not duplicate.
        $this->actingAs($user)->postJson(route('app.push-token'), ['token' => $token])->assertOk();
        $this->assertSame(1, DeviceToken::query()->count());
    }

    public function test_admin_notifications_page_and_config(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.notifications.index'))->assertOk();

        // Sending without Firebase config is refused.
        $this->actingAs($admin)->post(route('admin.notifications.send'), [
            'title' => 'Hi', 'body' => 'Test', 'audience' => 'all',
        ])->assertSessionHasErrors('title');

        // Invalid service-account JSON is rejected.
        $this->actingAs($admin)->post(route('admin.notifications.config'), ['fcm_service_account' => '{"foo":1}'])
            ->assertSessionHasErrors('fcm_service_account');

        // Valid shape is accepted.
        $json = json_encode([
            'type' => 'service_account', 'project_id' => 'demo', 'client_email' => 'x@demo.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMII\n-----END PRIVATE KEY-----\n",
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]);
        $this->actingAs($admin)->post(route('admin.notifications.config'), ['fcm_service_account' => $json])
            ->assertSessionHasNoErrors();
        $this->assertSame('demo', json_decode(Setting::get('fcm_service_account'), true)['project_id']);
    }

    public function test_google_services_upload_and_serving(): void
    {
        $this->get(route('google-services'))->assertNotFound();   // nothing uploaded yet

        $admin = $this->admin();
        $valid = json_encode([
            'project_info' => ['project_id' => 'demo'],
            'client' => [['client_info' => ['android_client_info' => ['package_name' => 'site.dramaearn.app']]]],
        ]);

        // Wrong package is rejected.
        $this->actingAs($admin)->post(route('admin.notifications.google-services'), [
            'google_services_json' => json_encode(['project_info' => ['project_id' => 'x'], 'client' => [['client_info' => ['android_client_info' => ['package_name' => 'com.other.app']]]]]),
        ])->assertSessionHasErrors('google_services_json');

        // Valid one is stored and then served publicly as JSON.
        $this->actingAs($admin)->post(route('admin.notifications.google-services'), ['google_services_json' => $valid])
            ->assertSessionHasNoErrors();
        $this->get(route('google-services'))->assertOk()->assertHeader('Content-Type', 'application/json')->assertSee('site.dramaearn.app');
    }

    public function test_color_setting_validation(): void
    {
        $admin = $this->admin();
        $values = Setting::allValues();
        $values['brand_color'] = 'notacolor';
        $this->actingAs($admin)->put(route('admin.settings.update'), $values)->assertSessionHasErrors('brand_color');

        $values['brand_color'] = '#00ff88';
        $this->actingAs($admin)->put(route('admin.settings.update'), $values)->assertSessionHasNoErrors();
        $this->assertSame('#00ff88', Setting::get('brand_color'));
    }
}
