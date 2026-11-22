# DramaEarn — Android App Guide

The app is a **WebView wrapper**: it opens your website (`dramaearn.site`) like an app on the phone.
Its biggest advantage: **any change you make on the website shows up in the app automatically — no need to rebuild the APK or make users download it again.**

A new APK is only needed when the app's own behaviour changes (domain, icon, or the notifications code).

---

## 1. How to build the APK (easiest — on GitHub, no Android Studio)

The APK builds itself in the cloud and lands in "Releases". You don't need to install anything on your computer.

1. Open the repo on GitHub → **Actions** tab.
2. Left side **"Build Android APK"** → right side **"Run workflow"**.
3. Enter a version (e.g. `1.0.0`) → **Run workflow** (green button).
4. In 3–5 minutes the build completes. Then open **Releases** (right side of the repo) — `dramaearn.apk` is there.
5. The landing page **"App download"** button already points to it — nothing to change.

> You must run the build once, otherwise the download button returns 404.

**Shortcut (via a tag):** on your computer:
```bash
git tag app-v1.0.0
git push origin app-v1.0.0
```
This runs the same build and creates a release.

---

## 2. Which website does the app open? (domain change)

Default is `https://dramaearn.site`. To change it:
- **Easy:** GitHub → repo → **Settings → Secrets and variables → Actions → Variables** → **New variable** → name `APP_URL`, value `https://your-domain.com`. The next build uses it.
- **Or in code:** change the `appUrl` default in `android/app/build.gradle`.

> If you change the domain, also set the `SITE_URL` variable (same place) so the build can download `google-services.json` from the new domain.

---

## 3. Signing key (recommended — so updates install without uninstalling)

The APK builds and installs even without a key (debug-signed). But if you want users to update the app in place, use your own signing key. One-time setup:

1. On your computer (with Java), create a key:
   ```bash
   keytool -genkey -v -keystore dramaearn.jks -keyalg RSA -keysize 2048 -validity 10000 -alias dramaearn
   ```
   Remember the password. **Keep this file and password safe** — if lost, future updates cannot use the same signature.
2. Convert the file to base64:
   - Linux/Mac: `base64 -w0 dramaearn.jks > key.txt`
   - Windows PowerShell: `[Convert]::ToBase64String([IO.File]::ReadAllBytes("dramaearn.jks")) > key.txt`
3. GitHub → repo → **Settings → Secrets and variables → Actions → Secrets** → add these 4 secrets:
   | Secret name | Value |
   |---|---|
   | `ANDROID_KEYSTORE_BASE64` | the full text of `key.txt` |
   | `ANDROID_KEYSTORE_PASSWORD` | keystore password |
   | `ANDROID_KEY_ALIAS` | `dramaearn` |
   | `ANDROID_KEY_PASSWORD` | key password (often the same) |
4. The next build is signed with this key.

---

## 4. Push notifications setup (Firebase)

Push needs Firebase. There are **two different JSON files** — don't mix them up:

| File | Purpose | Where it goes |
|---|---|---|
| **Service account JSON** | the server **sends** notifications | Admin → Notifications → step 1 (pasted) |
| **google-services.json** | the **APK receives** notifications | Admin → Notifications → step 2 (uploaded), then baked into the APK at build time |

### (a) Create the Firebase project and apps
1. Open [console.firebase.google.com](https://console.firebase.google.com) → create a project (or use an existing one).
2. In the project, **Add app → Android**, package name exactly: `site.dramaearn.app`
3. Download **google-services.json**.
4. Project settings → **Service accounts** → **Generate new private key** → download that JSON too.

### (b) Server: enable sending
1. Admin panel → **Notifications** → **step 1 (Service account)** → paste the service-account JSON → **Save service account**.

### (c) APK: enable receiving
1. Admin panel → **Notifications** → **step 2 (google-services.json)** → upload the `google-services.json` file.
   It is then served at `https://your-domain/google-services.json`.
2. **Rebuild the APK** (section 1). The build downloads `google-services.json` from your site automatically and bakes it in.
3. Users install the **new** APK. (The old APK has no Firebase, so it can't receive push.)

> Instead of the admin upload, you can set a GitHub secret `GOOGLE_SERVICES_JSON` with the file contents — the build uses that first if present.

A device starts receiving notifications once the user has: installed the new APK, logged in, and allowed notifications. The app hands its token to the website automatically — no manual step.

If Firebase isn't set, the app still works normally — only notifications are off.

---

## 5. App download button / version (from admin)

Admin → **Settings → Android App**:
- **Show the App download button** — on/off.
- **Version / size / min Android** — for display only.

The download happens from **your own domain** (`your-domain/download/apk`). The server streams the file from the source, so the user never sees where the file is actually hosted. The source link lives only in the server's private `.env` (`APK_SOURCE_URL`), which the installer sets automatically — it is not shown on any page or admin screen.

---

## FAQ

- **"Why isn't it on the Play Store?"** Putting it on the Play Store is better (more trust, no warning). For now the site offers a direct download.
- **"A new APK for every update?"** No. The app shows the website, so website changes appear automatically. A new APK is only for domain/icon/notification-code changes.
- **"Signature changed, the update won't install."** Uninstall the old app and install the new one. To avoid this, use your own signing key (section 3).
