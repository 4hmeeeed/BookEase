# Putting BookEase online (InfinityFree, free labnasan.* subdomain)

This guide puts BookEase on InfinityFree's free hosting at a free subdomain such as `https://labnasan.infinityfreeapp.com` (any free `labnasan.*` name InfinityFree offers), for real use by Labnasan Resort. Below, **SITE** means that address.

**How it will work there**
- **Guests pay by GCash/Maya to the resort's own wallet, or at the front desk.** InfinityFree blocks payment-gateway webhooks, so the gateway is off (`PAYMENT_DRIVER=none`). Guests send money to the resort's GCash/Maya number and upload the receipt; staff and the owner get a bell alert, check the wallet app, and press **Verify** (the guest gets an e-receipt) or **Not received** (the guest is emailed why). Cash, refunds and receipts work as before.
- **Email goes through Brevo's web API** (`MAIL_MAILER=brevo`), because InfinityFree blocks or times out SMTP ports.
- **There's no command line.** The owner's **System** page (`/admin/system`) does the few jobs that would need one: test email, database updates, clearing caches and the daily clean-up.
- **There's no cron.** The daily clean-up runs from the first visit of each day.
- **Phones must pass InfinityFree's browser check.** Every visitor's browser has to run InfinityFree's JavaScript check. Most phones pass, but some have been reported to fail. Test on a few phones before launch.

To turn on online payments later, see the last section.

---

## 1. Accounts (one time)

| What | Where | Notes |
|---|---|---|
| Hosting | infinityfree.com | Create an account, then a **hosting account**. In its control panel, set **PHP 8.2 or newer**. |
| Address | InfinityFree, when creating the hosting account | Choose **Subdomain** and type `labnasan`, then pick a free ending (e.g. `.infinityfreeapp.com`, `.rf.gd`). No domain to buy. A paid domain can be added later. |
| HTTPS | InfinityFree client area → **SSL certificates** | Free subdomains get HTTPS automatically. If SITE doesn't open with `https://`, issue the free certificate there and wait until it shows as installed. |
| Email sending | brevo.com (free, 300 emails/day) | Create an account. **Senders** → add and verify the resort's email address. **SMTP & API → API keys** → generate a key for `BREVO_API_KEY`. |
| GCash / Maya | the resort's own wallets | The numbers and account names guests send to (`GCASH_NUMBER`, `MAYA_NUMBER`…). Optional: save each wallet's QR code from the app as `gcash-qr.png` / `maya-qr.png`. |
| Google sign-in | console.cloud.google.com → project **BookEase Labnasan** | Credentials → the OAuth client → add the redirect URI `https://SITE/auth/google/callback`. Then Google Auth Platform → Audience → **Publish app**, so any guest can use it. |
| Gemini (chatbot) | aistudio.google.com | Reuse the existing BookEase key. |

## 2. Build the upload (on this laptop)

All work must be **committed** first: the bundle is built from the last commit.

```powershell
powershell -ExecutionPolicy Bypass -File scripts\make-deploy-bundle.ps1
```

This creates `deploy\htdocs\` (about 7,000 files). The script:
- installs the PHP packages without developer tools;
- builds the CSS/JS;
- adds the `.htaccess` that sends requests to `public\`, serves cottage photos, and **blocks `.env` and other hidden files**;
- stops if any PHP file is over InfinityFree's 1 MB limit.

## 3. Prepare the first database (on this laptop)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\prepare-production-db.ps1
```

Run it in a normal PowerShell window, with XAMPP's MySQL running. It does four things:
1. Creates `bookease_production` locally.
2. Creates all the tables. There are **no** sample cottages and **no** test accounts.
3. Asks for the **owner's** name, email and a strong password (12+ characters, letters and numbers).
4. Writes `deploy\bookease-production.sql`.

## 4. Create the database on InfinityFree

1. In the control panel, open **MySQL Databases** and create one (e.g. `bookease`). Note the **host** (`sqlNNN.infinityfree.com`), the database name (`if0_…_bookease`), the username and the password.
2. Open **phpMyAdmin** for it, choose **Import**, and upload `deploy\bookease-production.sql`.

## 5. Upload the files

1. In the control panel, open **FTP details**. Connect with **FileZilla**, and turn on *Server → Force showing hidden files* so `.htaccess` is visible.
2. Upload **everything inside** `deploy\htdocs\` into the server's `htdocs` folder. Don't upload the `htdocs` folder itself. Delete InfinityFree's default `index2.html` if it's there.

## 6. Create the settings file (`htdocs/.env`)

1. Copy `.env.production.example` to a new file named `.env`, and fill in every line marked **FILL IN**.
   - `APP_KEY`: run `php artisan key:generate --show` on the laptop and paste the result.
   - `DB_*`: from step 4.
   - `MAIL_FROM_ADDRESS` (the verified Brevo sender) and `BREVO_API_KEY`: from step 1.
   - `GCASH_*` / `MAYA_*`: the resort's wallet numbers and names. Leave a number empty to hide that wallet.
   - `GEMINI_API_KEY`, `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`, and the resort's real `RESORT_CONTACT_*` details.
2. Upload it as `htdocs/.env`.
3. Optional: upload the wallet QR codes to `htdocs/public/images/wallets/` as `gcash-qr.png` and `maya-qr.png`.

**Never** set `APP_DEBUG=true` on the live site. It shows secrets on error pages.

## 7. First checks

1. Open `https://SITE` on a computer **and on a phone**.
2. Sign in at `https://SITE/staff/login` with the owner account from step 3.
3. Open **`/admin/system`** and check:
   - PHP 8.2+, all extensions **OK**, all folders **Writable**, and no database updates waiting.
   - **"Your address as the app sees it"** should be your own internet address (search "what is my IP" to compare). If it shows another address, add `TRUSTED_PROXIES=*` to `.env`.
   - Press **Send a test email** and make sure it arrives.
4. Open `https://SITE/.env`. It must say **Forbidden** (403) or Not Found.
5. Try a full stay with a test guest account:
   1. Sign up and make a reservation.
   2. Staff approve it (the guest gets an email).
   3. As the guest, upload a GCash receipt screenshot; staff see the bell alert, press **Verify** (the guest gets a receipt). Try **Not received** on a second one (the guest gets the reason by email).
   4. Check the guest in and out.
6. Ask the chatbot a question, and try **Continue with Google**.

## 8. Before guests arrive

- The owner adds the real cottages, rates and photos (Owner console → Cottages) and the staff accounts (Owner console → Staff).
- The privacy notice (`/privacy`) must have the owner's final wording.

## Updating the site later

1. Commit the changes, then run step 2 again.
2. Upload the new `deploy\htdocs\` contents. **Keep** the server's `.env` and `storage\app\public` (uploaded photos). The bundle never contains a `.env`.
3. In `/admin/system`, press **Run database updates**, then **Clear caches**.

## If something goes wrong

- **"500 | Server error":**
  - Download `htdocs/storage/logs/laravel-YYYY-MM-DD.log` with FileZilla; the last lines say why.
  - Common causes: a wrong `DB_HOST` (it is never `localhost` on InfinityFree), or a missing `APP_KEY`.
- **Pages look old after an upload:** use **Clear caches**. Or, with FileZilla, empty `storage/framework/views` and `bootstrap/cache`, but keep the `.gitignore` files there.
- **Emails don't arrive:**
  - Use **Send a test email**; the error message names the problem.
  - Brevo: "Key not found" means a wrong `BREVO_API_KEY`; "sender not valid" means `MAIL_FROM_ADDRESS` isn't a verified Brevo sender.
- **A phone shows "This site requires JavaScript" or a 403:** this is InfinityFree's browser check, not BookEase. Try another browser. If it keeps happening, it's a reason to move to paid hosting.

## Turning on online payments later

This needs hosting that can receive the payment provider's webhooks (any paid shared hosting with SSH, e.g. Hostinger), plus the owner's **verified PayMongo business account**.
1. Set `PAYMENT_DRIVER=paymongo`, the `PAYMONGO_*` keys, and in PayMongo a webhook to `https://SITE/webhooks/payments` for `checkout_session.payment.paid`.
2. Keep `PAYMENT_ALLOW_LIVE_MODE=false` until test payments have been checked end to end. Only then switch to live keys.
