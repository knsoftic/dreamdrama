# Keep the JavaScript bridge methods used by the website.
-keepclassmembers class site.dramaearn.app.** {
    @android.webkit.JavascriptInterface <methods>;
}
