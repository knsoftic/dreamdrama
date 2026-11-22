package site.dramaearn.app;

import android.Manifest;
import android.annotation.SuppressLint;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.provider.Settings;
import android.view.KeyEvent;
import android.webkit.CookieManager;
import android.webkit.DownloadListener;
import android.webkit.JavascriptInterface;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.Toast;

import androidx.activity.result.ActivityResultLauncher;
import androidx.activity.result.contract.ActivityResultContracts;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.NotificationManagerCompat;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.google.firebase.messaging.FirebaseMessaging;

/**
 * A thin wrapper around the live website. Because it loads the site directly,
 * any change made on the website shows up in the app with no re-install.
 */
public class MainActivity extends AppCompatActivity {

    private WebView webView;
    private SwipeRefreshLayout refresh;
    private String host;
    private String pushToken = "";

    // Ask for the notification permission (Android 13+) and fetch the token once granted.
    private final ActivityResultLauncher<String> notifPermission = registerForActivityResult(
            new ActivityResultContracts.RequestPermission(),
            granted -> { if (granted) requestPushToken(); });

    private ValueCallback<Uri[]> fileCallback;
    private final ActivityResultLauncher<Intent> filePicker = registerForActivityResult(
            new ActivityResultContracts.StartActivityForResult(),
            result -> {
                Uri[] uris = null;
                if (result.getResultCode() == RESULT_OK && result.getData() != null) {
                    Uri data = result.getData().getData();
                    if (data != null) {
                        uris = new Uri[]{data};
                    }
                }
                if (fileCallback != null) {
                    fileCallback.onReceiveValue(uris);
                    fileCallback = null;
                }
            });

    @SuppressLint("SetJavaScriptEnabled")
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        String startUrl = getString(R.string.app_url);
        host = Uri.parse(startUrl).getHost();

        refresh = findViewById(R.id.refresh);
        webView = findViewById(R.id.webview);

        WebSettings s = webView.getSettings();
        s.setJavaScriptEnabled(true);
        s.setDomStorageEnabled(true);
        s.setDatabaseEnabled(true);
        s.setMediaPlaybackRequiresUserGesture(false);
        s.setLoadWithOverviewMode(true);
        s.setUseWideViewPort(true);
        s.setSupportMultipleWindows(false);
        s.setAllowFileAccess(false);
        s.setCacheMode(WebSettings.LOAD_DEFAULT);
        // Marks requests as coming from the app, so the website can adapt.
        s.setUserAgentString(s.getUserAgentString() + " DramaEarnApp/" + BuildConfig.VERSION_NAME);

        CookieManager.getInstance().setAcceptCookie(true);
        CookieManager.getInstance().setAcceptThirdPartyCookies(webView, true);

        webView.addJavascriptInterface(new AppBridge(), "DramaApp");

        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                return handleUrl(request.getUrl());
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                refresh.setRefreshing(false);
                // Hand the push token to the site so it can register this device.
                view.evaluateJavascript(
                        "window.__dramaPushToken && window.__dramaPushToken(" + jsString(pushToken) + ");", null);
            }
        });

        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public boolean onShowFileChooser(WebView webView, ValueCallback<Uri[]> callback, FileChooserParams params) {
                fileCallback = callback;
                try {
                    Intent intent = new Intent(Intent.ACTION_GET_CONTENT);
                    intent.addCategory(Intent.CATEGORY_OPENABLE);
                    intent.setType(params.getAcceptTypes().length > 0 && !params.getAcceptTypes()[0].isEmpty()
                            ? params.getAcceptTypes()[0] : "*/*");
                    filePicker.launch(Intent.createChooser(intent, "Select"));
                } catch (Exception e) {
                    fileCallback = null;
                    return false;
                }
                return true;
            }
        });

        webView.setDownloadListener(new DownloadListener() {
            @Override
            public void onDownloadStart(String url, String ua, String disp, String mime, long size) {
                openExternally(Uri.parse(url));
            }
        });

        // Pull to refresh; only active when the page is scrolled to the top.
        refresh.setOnRefreshListener(() -> webView.reload());
        webView.getViewTreeObserver().addOnScrollChangedListener(() ->
                refresh.setEnabled(webView.getScrollY() == 0));

        requestPushToken();
        askNotificationsOnLaunch();

        if (savedInstanceState != null) {
            webView.restoreState(savedInstanceState);
        } else {
            Uri data = getIntent().getData();
            webView.loadUrl(data != null && isOurHost(data) ? data.toString() : startUrl);
        }
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        if (intent.getData() != null && isOurHost(intent.getData()) && webView != null) {
            webView.loadUrl(intent.getData().toString());
        }
    }

    private boolean isOurHost(Uri uri) {
        String uHost = uri.getHost();
        return uHost != null && host != null && (uHost.equals(host) || uHost.endsWith("." + host));
    }

    /** True = we handled the URL (open outside the app). False = let the WebView load it. */
    private boolean handleUrl(Uri uri) {
        String scheme = uri.getScheme() == null ? "" : uri.getScheme();
        String uHost = uri.getHost();

        // Our own site stays in the WebView.
        if (("http".equals(scheme) || "https".equals(scheme)) && uHost != null && host != null
                && (uHost.equals(host) || uHost.endsWith("." + host))) {
            return false;
        }
        // Everything else (tel:, mailto:, whatsapp:, market:, other sites) opens outside.
        openExternally(uri);
        return true;
    }

    private void openExternally(Uri uri) {
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, uri));
        } catch (Exception e) {
            Toast.makeText(this, "No app found to open this.", Toast.LENGTH_SHORT).show();
        }
    }

    /** On a fresh launch, show the notification permission dialog once (Android 13+). */
    private void askNotificationsOnLaunch() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
            return;   // older Android allows notifications by default, no prompt
        }
        if (checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED) {
            return;
        }
        SharedPreferences prefs = getSharedPreferences("app", MODE_PRIVATE);
        // If permanently denied, don't nag on every launch — the in-app button sends to settings.
        if (prefs.getBoolean("notif_asked", false)
                && ! shouldShowRequestPermissionRationale(Manifest.permission.POST_NOTIFICATIONS)) {
            return;
        }
        prefs.edit().putBoolean("notif_asked", true).apply();
        notifPermission.launch(Manifest.permission.POST_NOTIFICATIONS);
    }

    /** Called from a website button: request the permission, or open settings if blocked. */
    private void turnOnNotifications() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU
                && checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED) {
            SharedPreferences prefs = getSharedPreferences("app", MODE_PRIVATE);
            if (shouldShowRequestPermissionRationale(Manifest.permission.POST_NOTIFICATIONS)
                    || ! prefs.getBoolean("notif_asked", false)) {
                prefs.edit().putBoolean("notif_asked", true).apply();
                notifPermission.launch(Manifest.permission.POST_NOTIFICATIONS);
            } else {
                openNotificationSettings();
            }
            return;
        }
        if (! NotificationManagerCompat.from(this).areNotificationsEnabled()) {
            openNotificationSettings();
        } else {
            requestPushToken();
        }
    }

    private void openNotificationSettings() {
        try {
            Intent intent;
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                intent = new Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS)
                        .putExtra(Settings.EXTRA_APP_PACKAGE, getPackageName());
            } else {
                intent = new Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:" + getPackageName()));
            }
            startActivity(intent);
        } catch (Exception ignored) {
        }
    }

    private void requestPushToken() {
        try {
            FirebaseMessaging.getInstance().getToken().addOnCompleteListener(task -> {
                if (task.isSuccessful() && task.getResult() != null) {
                    pushToken = task.getResult();
                    if (webView != null) {
                        webView.evaluateJavascript(
                                "window.__dramaPushToken && window.__dramaPushToken(" + jsString(pushToken) + ");", null);
                    }
                }
            });
        } catch (Exception ignored) {
            // Firebase not configured yet — app works fine without notifications.
        }
    }

    private static String jsString(String value) {
        if (value == null) return "''";
        return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'";
    }

    @Override
    protected void onSaveInstanceState(Bundle outState) {
        super.onSaveInstanceState(outState);
        webView.saveState(outState);
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        if (keyCode == KeyEvent.KEYCODE_BACK && webView.canGoBack()) {
            webView.goBack();
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }

    /** Methods the website can call via window.DramaApp.*. */
    private class AppBridge {
        @JavascriptInterface
        public boolean isApp() {
            return true;
        }

        @JavascriptInterface
        public String appVersion() {
            return BuildConfig.VERSION_NAME;
        }

        @JavascriptInterface
        public String getPushToken() {
            return pushToken;
        }

        /** The website can call this (e.g. an "Enable notifications" button). */
        @JavascriptInterface
        public void enableNotifications() {
            runOnUiThread(MainActivity.this::turnOnNotifications);
        }

        @JavascriptInterface
        public boolean notificationsAllowed() {
            return NotificationManagerCompat.from(MainActivity.this).areNotificationsEnabled();
        }
    }
}
