# DramaEarn — Android App Guide (Roman Urdu)

App ek **WebView wrapper** hai: yeh aap ki website (`dramaearn.site`) ko phone mein app ki tarah kholti hai.
Iska sabse bara faida: **website par koi bhi change karo — app mein khud aa jata hai. APK dobara banane ya users ko dobara download karwane ki zaroorat nahi.**

Nayi APK sirf tab chahiye jab app ka apna behaviour badle (domain, icon, ya notifications wala code).

---

## 1. APK kaise banayein (sabse aasan — GitHub par, bina Android Studio)

APK cloud mein khud ban jati hai aur "Releases" mein aa jati hai. Aapke computer par kuch install karne ki zaroorat nahi.

1. GitHub par repo kholein → upar **Actions** tab.
2. Left side **"Build Android APK"** → right side **"Run workflow"**.
3. Version likhein (e.g. `1.0.0`) → **Run workflow** (green button).
4. 3–5 minute mein build complete. Phir **Releases** (repo ke right side) mein jayein — wahan `dramaearn.apk` mojood hoga.
5. Landing page ka **"App download"** button khud isi par point karta hai (`releases/latest/download/dramaearn.apk`) — kuch change karne ki zaroorat nahi.

> Pehli dafa build chalana zaroori hai, warna download button 404 dega.

**Chhota tareeqa (tag se):** apne computer par:
```bash
git tag app-v1.0.0
git push origin app-v1.0.0
```
Yeh bhi wahi build chala kar release bana deta hai.

---

## 2. App kis website ko kholti hai? (domain change)

Default `https://dramaearn.site` hai. Badalna ho to:
- **Aasan:** GitHub → repo → **Settings → Secrets and variables → Actions → Variables** → **New variable** → naam `APP_URL`, value `https://aap-ka-domain.com`. Agli build isi ko use karegi.
- **Ya code mein:** `android/app/build.gradle` mein `appUrl` ki default value badal dein.

---

## 3. Signing key (recommended — taake updates bina uninstall ke lagein)

Bina key ke bhi APK banti aur install hoti hai (debug-signed). Lekin agar aap chahte hain ke user purani app ke upar nayi app seedha update kar sake, to ek apni signing key rakhein. Ek dafa ka kaam:

1. Apne computer par (jahan Java ho) key banayein:
   ```bash
   keytool -genkey -v -keystore dramaearn.jks -keyalg RSA -keysize 2048 -validity 10000 -alias dramaearn
   ```
   Password yaad rakhein. **Yeh file aur password sambhal kar rakhein** — kho gaye to future updates isi signature se nahi ho payenge.
2. File ko base64 mein convert karein:
   - Linux/Mac: `base64 -w0 dramaearn.jks > key.txt`
   - Windows PowerShell: `[Convert]::ToBase64String([IO.File]::ReadAllBytes("dramaearn.jks")) > key.txt`
3. GitHub → repo → **Settings → Secrets and variables → Actions → Secrets** → ye 4 secrets banayein:
   | Secret naam | Value |
   |---|---|
   | `ANDROID_KEYSTORE_BASE64` | `key.txt` ka poora text |
   | `ANDROID_KEYSTORE_PASSWORD` | keystore password |
   | `ANDROID_KEY_ALIAS` | `dramaearn` |
   | `ANDROID_KEY_PASSWORD` | key password (aksar wahi) |
4. Agli build is key se signed hogi.

---

## 4. Push Notifications setup (Firebase)

Notifications ke liye Firebase chahiye. Do cheezein chahiye hoti hain: app ke liye `google-services.json`, aur server (admin panel) ke liye service-account JSON.

### (a) Firebase project
1. [console.firebase.google.com](https://console.firebase.google.com) → **Add project** (naam kuch bhi).
2. Project mein **Add app → Android** chunein.
3. **Package name** bilkul yeh daalein: `site.dramaearn.app`
4. **google-services.json** download karein.

### (b) App ko Firebase se jodna (GitHub secret)
1. Us `google-services.json` ki poori file ka text copy karein.
2. GitHub → **Settings → Secrets → Actions** → secret `GOOGLE_SERVICES_JSON` banayein, value = woh poora JSON.
3. Agli APK build is config ke saath banegi (notifications tabhi chalengi).

### (c) Admin panel ko notifications bhejne layak banana
1. Firebase Console → **Project settings → Service accounts → Generate new private key** → JSON download.
2. DramaEarn admin panel → **Notifications** → "Firebase setup" box mein us JSON ko paste kar ke **Save config**.
3. Ab admin **Notifications** page se title/message likh kar sab users ko notification bhej sakta hai.

> Notification tab milti hai jab: user ne app install ki ho, login kiya ho, aur notifications allow kiye hon. App khud token website ko de deti hai (koi manual kaam nahi).

Firebase set na ho to bhi app theek chalti hai — sirf notifications band rehti hain.

---

## 5. App ka download button / version (admin se)

Admin → **Settings → Android App**:
- **App download button dikhayein** — on/off.
- **Version / size / min Android** — sirf dikhane ke liye.

Download **aap ke apne domain** se hota hai (`aap-ka-domain/download/apk`). Server file ko source se le kar stream karta hai, is liye user ko kabhi yeh nazar nahi aata ke file asal mein kahan se aa rahi hai. Source link sirf server ki private `.env` (`APK_SOURCE_URL`) mein rehta hai aur installer ise khud set kar deta hai — kisi page ya admin screen par nahi dikhता.

---

## Aksar poochhe jane wale

- **"App Play Store par kyun nahi?"** Play Store par daalna behtar hai (trust zyada, warning nahi aati). Guide isi folder mein baad mein add kar denge. Abhi website se direct download chalega.
- **"Har update par nayi APK?"** Nahi. App website dikhati hai, is liye website ke changes khud aate hain. Nayi APK sirf domain/icon/notification code badalne par.
- **"Signature change ho gaya, update nahi lag raha"** Purani app uninstall kar ke nayi install karein. Isi se bachne ke liye section 3 (apni key) use karein.
