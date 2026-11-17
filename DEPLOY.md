# Putting BookEase online (InfinityFree, labnasanresort.com)

This guide puts BookEase on InfinityFree's free hosting at `https://labnasanresort.com`, for real use by Labnasan Resort.

**How it will work there**
- **Guests pay at the resort's front desk.** InfinityFree blocks the payment provider's confirmation calls, so online GCash/Maya is switched off (`PAYMENT_DRIVER=none`). Staff record cash payments, refunds and receipts as usual.
- **There's no command line.** The owner's **System** page (`/admin/system`) does the few jobs that would need one: test email, database updates, clearing caches and the daily clean-up.
- **There's no cron.** The daily clean-up runs from the first visit of each day.
- **Phones must pass InfinityFree's browser check.** Every visitor's browser has to run InfinityFree's JavaScript check. Most phones pass, but some have been reported to fail. Test on a few phones before launch.

To turn on online payments later, see the last section.

---

## 1. Accounts (one time)

| What | Where | Notes |
|---|---|---|
| Hosting | infinityfree.com | Create an account, then a **hosting account**. In its control panel, set **PHP 8.2 or newer**. |
| Domain | a registrar (Porkbun, Namecheap, Cloudflare…) | Buy **labnasanresort.com** (about $10/year), ideally in the resort owner's name. Then add it to the InfinityFree hosting account and set the domain's **nameservers** to the ones InfinityFree shows. |
| HTTPS | InfinityFree client area → **SSL certificates** | Issue the free certificate for `labnasanresort.com` and `www.labnasanresort.com`. Wait until it shows as installed. |
| Email sending | Gmail **or** Brevo | **Gmail:** use a resort Gmail account. Turn on 2-Step Verification, then create an **app password** (Google Account → Security → App passwords). **Brevo** (free, 300 emails/day): create an account and get its SMTP login and key. |
| Google sign-in | console.cloud.google.com → project **BookEase Labnasan** | Credentials → the OAuth client → add the redirect URI `https://labnasanresort.com/auth/google/callback`. Then Google Auth Platform → Audience → **Publish app**, so any guest can use it. |
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
   - `MAIL_*`: from step 1.
   - `GEMINI_API_KEY`, `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET`, and the resort's real `RESORT_CONTACT_*` details.
2. Upload it as `htdocs/.env`.

**Never** set `APP_DEBUG=true` on the live site. It shows secrets on error pages.

## 7. First checks

1. Open `https://labnasanresort.com` on a computer **and on a phone**.
2. Sign in at `https://labnasanresort.com/staff/login` with the owner account from step 3.
3. Open **`/admin/system`** and check:
   - PHP 8.2+, all extensions **OK**, all folders **Writable**, and no database updates waiting.
   - **"Your address as the app sees it"** should be your own internet address (search "what is my IP" to compare). If it shows another address, add `TRUSTED_PROXIES=*` to `.env`.
   - Press **Send a test email** and make sure it arrives.
4. Open `https://labnasanresort.com/.env`. It must say **Forbidden** (403) or Not Found.
5. Try a full stay with a test guest account:
   1. Sign up and make a reservation.
   2. Staff approve it (the guest gets an email).
   3. Record a cash payment (the guest gets a receipt).
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
  - For Gmail, the password must be an *app password*, and `MAIL_FROM_ADDRESS` must be the same Gmail address.
- **A phone shows "This site requires JavaScript" or a 403:** this is InfinityFree's browser check, not BookEase. Try another browser. If it keeps happening, it's a reason to move to paid hosting.

## Turning on online payments later

This needs hosting that can receive the payment provider's webhooks (any paid shared hosting with SSH, e.g. Hostinger), plus the owner's **verified PayMongo business account**.
1. Set `PAYMENT_DRIVER=paymongo`, the `PAYMONGO_*` keys, and in PayMongo a webhook to `https://labnasanresort.com/webhooks/payments` for `checkout_session.payment.paid`.
2. Keep `PAYMENT_ALLOW_LIVE_MODE=false` until test payments have been checked end to end. Only then switch to live keys.
