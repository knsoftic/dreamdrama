# DramaEarn — aaPanel par Deployment Guide

| | |
|---|---|
| **Domain** | `dramaearn.site` |
| **Website folder** | `/www/wwwroot/dramaearn.site` |
| **GitHub repo** | https://github.com/knsoftic/dreamdrama |
| **Panel** | aaPanel (Nginx + MySQL + PHP) |

Poora setup **ek command** se hota hai. Wahi command baad mein har update ke liye bhi chalti hai.

---

## 1. Pehle aaPanel mein yeh tayyar karein (sirf ek dafa)

1. **App Store** mein yeh installed hon: **Nginx**, **MySQL** (5.7+ / MariaDB 10.3+), **PHP 8.2 ya 8.3**.
2. **PHP extension `fileinfo` (zaroori):**
   App Store → PHP-8.x → **Setting** → **Install extensions** → `fileinfo` → Install.
   (Is ke baghair video upload nahi hogi aur installer ruk jayega.)
3. **Website:** Website → **Add site** → domain `dramaearn.site`, PHP version **8.2/8.3** (Static nahi).
4. **Database:** Databases → **Add database** → database name, username aur password note kar lein.
5. **DNS:** domain ka `A` record server ke IP par ho.
6. **SSL (recommended):** Website → `dramaearn.site` → **SSL** → Let's Encrypt → Apply → **Force HTTPS** on.
   Installer ke "SSL laga hai?" wale sawal ka jawab `Y` dein.

---

## 2. Single command (install + update)

aaPanel → **Terminal** kholein (ya SSH se `root` login karein) aur yeh chalayein:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/knsoftic/dreamdrama/main/deploy/install.sh)
```

Bas. Script khud check karti hai ke pehli dafa install hai ya update.

---

## 3. Pehli dafa script yeh poochegi

| Sawal | Kya likhein |
|---|---|
| Domain | `Enter` dabayein (default: `dramaearn.site`) |
| Website folder | `Enter` dabayein (default: `/www/wwwroot/dramaearn.site`) |
| Site ka naam | e.g. `DramaEarn` (app mein har jagah yahi naam dikhega) |
| SSL (https) laga hai? | `Y` (agar SSL lagaya hai ya lagayenge) |
| Database name | aaPanel mein banaya hua database |
| Database username | `Enter` agar username database name jaisa hai (aaPanel ka default) |
| Database password | password (type karte waqt nazar nahi aata) |
| Admin ka naam | e.g. `Admin` |
| Admin email | is email se admin login karega |
| Admin mobile | optional — `Enter` se skip |
| Admin password | kam az kam 8 characters, do dafa |
| Nginx khud set karun? | `Y` |
| PHP upload limit 256MB karun? | `Y` |

Database ki details ghalat hon to script wahin bata degi aur dobara poochegi.

---

## 4. Script khud kya karti hai

1. Domain / folder detect karti hai, aur dekhti hai ke install hai ya update.
2. Server check: website ka PHP version (8.2+), zaroori extensions, `git` / `curl` / `unzip` (na hon to khud install).
3. GitHub se latest code laati hai (aaPanel ki `.user.ini` jaisi files ko chhede baghair).
4. Composer download kar ke PHP packages install karti hai (`--no-dev`, optimized).
5. `.env` banati hai (production mode, debug off, database, https, `APP_KEY`).
6. Database tables (migrations), starter plans (Free / Silver / Gold), aur admin account.
7. `storage` link, config/route/view cache, aur `www` user ki permissions.
8. **Nginx:** website root `/public` par, Laravel URL rewrite, 256MB upload. Pehle backup banati hai, aur `nginx -t` fail ho to purani config wapas laga deti hai. PHP upload limit bhi 256MB karti hai.
9. Har minute chalne wala cron (subscription expire waghaira) lagati hai, website ko check karti hai, aur summary dikhati hai.

---

## 5. Update kaise karein (har GitHub push ke baad)

Wahi command dobara chalayein:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/knsoftic/dreamdrama/main/deploy/install.sh)
```

Domain aur folder ke liye 2 dafa `Enter` dabayein. Script kuch aur nahi poochegi.

- Latest code aata hai, packages update hote hain, nayi migrations chalti hain, aur cache refresh hota hai.
- `.env`, uploaded videos (`storage/app/public`) aur database **safe rehte hain**.
- ⚠️ Server par Git wali files mein haath se ki gayi tabdeeli update par khatam ho jati hai. Changes hamesha GitHub ke zariye karein.
- CSS/JS ki build (`public/build`) repo mein commit hoti hai, is liye **server par Node.js ki zaroorat nahi**. Local mein design change ke baad `npm run build` kar ke commit karna zaroori hai.

---

## 6. Install ke baad (Admin panel)

`https://dramaearn.site/admin` par apne admin email/password se login karein, phir:

1. **Settings → General:** site naam, support WhatsApp number.
2. **Settings → Earning & Cycle:** per-second rate, per-ad rate, cycle mein videos/ads, ad duration.
3. **Settings → Subscription Payments:** apna JazzCash / Easypaisa number aur account title (abhi sample text likha hai).
4. **Plans:** prices aur limits apne hisaab se.
5. **Videos:** asli videos upload karein (vertical 9:16 best).
6. **Ad Units:** Adsterra / Monetag ka ad code paste karein.
7. **Settings → Ad Network Scripts:** network ki site-verification meta tag / Social Bar script.
   Agar network `ads.txt` maange to file `public/ads.txt` mein rakhni hogi. Mujhe bata dein, main repo mein add kar dunga.

---

## 7. Manual steps (agar script web server set na kar paye)

aaPanel → Website → `dramaearn.site` → **Conf**:

1. **Site directory** → Running directory: `/public` → Save.
2. **URL rewrite** → `laravel5` select → Save.
3. **Config file** mein `server {` ke andar add karein: `client_max_body_size 256m;`
4. App Store → PHP-8.x → Setting → **Configuration**: `upload_max_filesize = 256M`, `post_max_size = 256M`.
5. **Cron** → Add → Type: Shell Script, har 1 minute:
   ```bash
   cd /www/wwwroot/dramaearn.site && /www/server/php/82/bin/php artisan schedule:run
   ```
   (`82` ki jagah apna PHP version likhein, e.g. `83`.)

---

## 8. Masle aur un ka hal

| Masla | Hal |
|---|---|
| aaPanel ka default "Congratulations" page dikhta hai | Running directory `/public` nahi hai (section 7, step 1) |
| `/login` par 404 | URL rewrite `laravel5` set nahi (section 7, step 2) |
| 500 Server Error | `tail -n 50 /www/wwwroot/dramaearn.site/storage/logs/laravel.log` dekhein |
| `requires ext-fileinfo` / "Unable to guess the MIME type" | PHP extension `fileinfo` install karein (section 1) |
| Video upload par `413 Request Entity Too Large` | `client_max_body_size` aur PHP upload limit (section 7, steps 3–4) |
| `Permission denied` (storage / logs) | `chown -R www:www /www/wwwroot/dramaearn.site && chmod -R 775 /www/wwwroot/dramaearn.site/storage /www/wwwroot/dramaearn.site/bootstrap/cache` |
| Database connection error | `.env` mein `DB_*` theek karein, phir neeche wali cache command chalayein |
| Links `http` par jaa rahe hain | `.env` mein `APP_URL=https://dramaearn.site`, phir cache command |
| **Cloudflare** use kar rahe hain aur naye users "Is network se mazeed accounts nahi ban sakte" dekh rahe hain | Cloudflare ki wajah se sab ka IP ek jaisa dikhta hai. Admin → Settings → "Ek IP se max accounts" ko `0` kar dein, ya Nginx mein Cloudflare real-IP set karein |

---

## 9. Kaam ki commands

Sab commands website folder mein chalayein: `cd /www/wwwroot/dramaearn.site`.
Agar `php` command na mile to us ki jagah `/www/server/php/82/bin/php` likhein.

```bash
# .env badalne ke baad cache refresh
php artisan optimize:clear && php artisan optimize
```

```bash
# Admin ka password bhool gaye / naya admin (password poochega)
php artisan app:admin --email=aap@example.com
```

```bash
# Website maintenance mode on / off
php artisan down
```

```bash
php artisan up
```

```bash
# Latest errors
tail -n 100 storage/logs/laravel.log
```

---

## 10. Backup

- **Database:** aaPanel → Databases → Backup. Cron mein "Backup Database" roz ka laga dein.
- **Files:** `storage/app/public` (videos, thumbnails, payment screenshots) aur `.env`.
  Code GitHub par hai, us ka alag backup zaroori nahi.

---

## Security

- `.env` kabhi GitHub par nahi jati (`.gitignore` mein hai). Is mein database password aur `APP_KEY` hain.
- Production mein `APP_DEBUG=false` rehna chahiye (installer yahi set karta hai).
- Admin ka password mazboot rakhein. Withdrawals hamesha check kar ke approve karein.
