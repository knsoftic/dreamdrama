# Payment Gateway (PKPay) — Setup Guide (Roman Urdu)

Plan purchase ab **PKPay (Raast QR)** gateway se ho sakta hai. User QR scan kar ke pay karta hai aur plan **foran** active ho jata hai. Manual payment (TID + admin approve) bhi fallback ke tor par mojood rehta hai.

## Kaise chalta hai
1. User checkout par **"abhi pay karein"** dabata hai.
2. Humara server PKPay par ek payment banata hai aur user ko gateway ke QR page par bhej deta hai.
3. User JazzCash / Easypaisa / bank app se QR scan kar ke pay karta hai (QR 5 min mein expire hota hai).
4. PKPay humein ek **signed callback** bhejta hai. Hum signature verify karte hain, amount match karte hain, aur plan active kar dete hain.
5. User wapas aata hai to "Payment mukammal" page dikhता hai. (Callback miss ho jaye to return page aur admin ka "Check status" dono dobara verify kar lete hain.)

## Admin setup (Admin → Payment Gateway)
1. **PKPay merchant portal** se **API key** (`pk_…`) aur **API secret** (`sk_…`) lein.
2. Admin → **Payment Gateway** mein:
   - **Plan purchase ka tareeqa:** Manual / Sirf gateway / Dono.
   - **Gateway on** check karein.
   - API base URL (default `https://pkpay.site/api/v1`), API key, API secret daal kar **Save**.
3. **IP allowlist (zaroori):** PKPay admin ko apne **server ka IP** dein (Pay-In side). Warna `ip_not_allowed` error aayega. Server ka IP: `curl ifconfig.me` se mil jata hai.
4. **Test connection** dabayein — "connected ✓" aa jaye to set hai.

> Callback URL khud create-payment ke saath bhej diya jata hai: `https://dramaearn.site/pkpay/callback`. PKPay portal is URL ko extra set karne ki zaroorat nahi, lekin maange to yahi dein.

## Security
- API secret **server par rehti hai**, browser ya app ko kabhi nahi jati, aur admin page par dobara dikhti nahi (sirf "saved").
- Har callback **HMAC-SHA256 signature** se verify hota hai; ghalat signature reject.
- **Sandbox** callbacks (test) se plan **active nahi** hota.
- Plan tabhi active hota hai jab **amount exactly match** kare aur status `paid` ho.
- Payment records securely DB mein store hote hain (`payments` table mein gateway id, status, verified snapshot).
- Callback route CSRF-exempt hai (signature se protected) aur rate-limited.

## Manual approval (fallback)
- Mode "Manual" ya "Dono" mein user TID/screenshot submit karta hai.
- Admin → **Payments** se Approve/Reject. Gateway payment agar callback miss kare to wahan **"Check status"** button se dobara verify ho jata hai.

## Withdrawals / Payouts
Docs mein payout (merchant apni earning nikaale) API bhi hai. Abhi integrate nahi kiya — users ko paisa aap apne JazzCash/Easypaisa se dete hain (app ka apna withdrawal system). Agar chahein ke user payouts bhi isi gateway se automatic hon to bata dein, add kar dunga.

## Important
- Amount PKR mein 1–500,000 ke beech hona chahiye. Plan prices isi range mein rakhein.
- Gateway live tabhi hoga jab credentials set hon, "Gateway on" ho, aur server IP allowlist mein ho. Inme se kuch missing ho to app khud manual par chalti rahegi.
