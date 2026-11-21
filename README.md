# PlayEarn — Watch & Earn Platform (Laravel 12)

Users short videos dekhte hain, ad breaks dekhte hain, aur earning wallet mein aati hai. Admin rates, plans, ads aur withdrawals control karta hai.

## Live server (aaPanel) par install / update

Poori guide: **[DEPLOYMENT.md](DEPLOYMENT.md)**. Server ke Terminal mein (root) bas yeh ek command:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/knsoftic/dreamdrama/main/deploy/install.sh)
```

> CSS/JS badalne ke baad `npm run build` kar ke `public/build` bhi commit karein — server par Node.js nahi chahiye.

## Local chalane ka tareeqa (XAMPP, Windows)

1. XAMPP Control Panel se **MySQL** start karein (database: `playearn`).
2. Project folder mein:
   ```bash
   php artisan serve
   ```
3. Browser: http://127.0.0.1:8000
4. CSS/JS mein tabdeeli ke baad: `npm run build` (ya development ke dauran `npm run dev`).

Pehli dafa naye computer par setup:
```bash
composer install
npm install && npm run build
php artisan migrate --seed
php artisan storage:link
```

**Admin login:** `.env` file mein `ADMIN_EMAIL` aur `ADMIN_PASSWORD`. Admin panel: `/admin`.

## Earning ka system

- **Cycle** = X videos + Y ads (Admin → Settings → Earning & Cycle). Default 10 + 10.
- **Video earning** = dekhe gaye seconds × `rate_per_second` × plan multiplier. Seconds server par heartbeats se gine jate hain (browser par bharosa nahi).
- **Ad earning** = har ad ka fixed `rate_per_ad` × plan multiplier. Ad tabhi count hota hai jab countdown poora ho.
- Cycle ki saari earning **pending** rehti hai; cycle poori hone (saare ads samet) par hi wallet mein credit hoti hai.
- Plan: roz ki cycles ki limit, multiplier, daily cap, min withdrawal.

## Fraud se hifazat
- Ek waqt mein sirf ek video earn karti hai; heartbeat ek dafa mein max (heartbeat + 3) seconds jorta hai; pause ka waqt count nahi hota.
- Ek video par ek hi dafa earning (setting).
- Ek IP se max accounts (setting), same device par kai accounts → user flag + Fraud Reports.
- Withdrawals admin manual approve karta hai; reject karne par paise wapas wallet mein.

## Ads (Adsterra / Monetag)
- **Admin → Ad Units**: banner / native ad code paste karein. "Ad Break" placement videos ke beech full-screen dikhta hai, "Page Banner" wallet/earnings pages par.
- **Admin → Settings → Ad Network Scripts**: verification tag, Social Bar jaisi global scripts.
- ⚠️ Ad par click karne ka reward kabhi na dein. Apne ad network se confirm karein ke rewarded/incentivized model allowed hai.

## Important files
| Kaam | File |
|---|---|
| Earning engine (cycle, heartbeat, ads) | `app/Services/EarningService.php` |
| Wallet ledger | `app/Services/WalletService.php` |
| Admin settings ki list | `config/platform.php` |
| Video feed (mobile/desktop) | `resources/views/feed/index.blade.php`, `resources/js/feed.js` |
| App layout (bottom tab bar / sidebar) | `resources/views/layouts/app.blade.php` |
| Admin panel | `app/Http/Controllers/Admin/*`, `resources/views/admin/*` |
| Routes | `routes/web.php` |

## Tests
```bash
php artisan test
```
