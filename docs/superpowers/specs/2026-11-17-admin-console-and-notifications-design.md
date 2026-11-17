# Admin console, notifications, and mobile — design

Date: 2026-11-17 · Status: **approved by the user 2026-11-17** · Size: project (backend + frontend)

## Intent

The user asked for three things: make the admin "much more elegant", add a notification
feature, and check whether BookEase has a mobile UI/UX.

What the code shows today:

- Staff and the owner only learn about a new request, an online payment, or a guest
  cancellation by opening the booking queue. The only notifications are emails to guests
  (`PaymentReceived`, `BookingStatusChanged`, sent with `mail` only; `MAIL_MAILER=log` in dev).
- A GCash/Maya payment that arrives with the wrong amount is only written to the log
  (`PaymentService::handleWebhook`, "mismatch"), so nobody is told to review it.
- The owner gets 7 links in one flat top bar, which turns into a hamburger list below 1280px.
  The owner overview repeats the front-desk tiles, and its five "Manage" cards repeat the menu.
- There is no separate mobile app; BookEase is one responsive site. Console pages stack on
  phones (card lists, no wide tables) but were not designed for them: long hamburger list,
  booking actions out of thumb reach, no bottom navigation.

Outcome wanted: a calm, elegant console for staff and the owner that tells them what needs
attention, with in-app notifications, and that works well on a phone.

## Decisions made with the user

| Question | Answer |
|---|---|
| Which events notify the resort side? | "Everything": new request, online payment (and wrong-amount payments), guest cancellation, chatbot couldn't answer |
| Delivery | In-app bell that checks every 30 s (no email copies, no websockets) |
| Redesign scope | New console layout + rebuilt owner overview (other admin pages get the new frame only) |
| Guests | Staff & owner only; guests keep their emails |

**My call (correct me):** "everything" also covers telling the **owner** when staff record a
**cash payment or a refund** (money oversight). Routine staff actions (approve, reject,
check-in/out) go to the owner's **Recent activity** feed instead of the bell, to keep the bell quiet.

## Goals

1. Staff and the owner see new requests, online payments, wrong-amount payments, guest
   cancellations and chatbot gaps without checking the queue: bell + unread count, dropdown,
   full list page, auto-check every 30 s.
2. Console layout for staff and the owner: grouped sidebar on desktop, top bar with booking
   search and bell, bottom tab bar on phones.
3. Owner overview rebuilt around **Needs attention** → key numbers → 30-day chart → **Recent activity**.
4. Front desk, booking queue and booking page polished; booking actions reachable by thumb on phones.
5. Console-wide refinements: calmer motion, tabular figures, refined booking codes, Owner chip
   fix, nautical empty states.

## Non-goals

- In-app notifications for guests; email/SMS/push copies to staff; real-time websockets.
- Redesigning the insides of Reports, Cottages, Staff and Chatbot log pages (they get the new
  frame and shared styles only).
- A native app or offline mode (CLAUDE.md: no offline mode).
- Any change to availability, booking-lifecycle or payment rules.

## Anti-goals (failure even if it "works")

- A notification failure blocks or undoes a booking, payment or decision. (Same rule as the guest
  emails: send after commit, catch and log.)
- A noisy bell: people notified about their own actions, or one alert per chatbot miss.
- Data leaking across roles: guests seeing console notifications, or a user reading or marking
  someone else's.
- Polling that keeps an unattended console signed in forever, or hammers the single-threaded
  dev server.
- Heavy JS libraries, generic dashboard look, `transition-all`, ignoring reduced motion.

## Constraints

- Laravel 12, Blade, Tailwind 3.4, Alpine; no Livewire/Vue/React. Money via `App\Support\Money` (bcmath).
- Design system rules (memory `frontend-design-conventions`): lagoon ink for money, **coral only for
  warnings / balances owed**, Caslon headings + Hanken Grotesk body, tight corners, explicit
  transitions, `prefers-reduced-motion`, `short:` variant for short laptop screens.
- Dev box: `php artisan serve` is single-threaded and RAM is very low; `BROADCAST_CONNECTION=log`;
  `QUEUE_CONNECTION=database` but notifications stay synchronous (no worker needed).
- Data Privacy Act: notification data holds only what's needed (booking reference, guest name,
  unit, dates, amount). Wallet details never appear.
- Tests run on in-memory SQLite. `AdminReportsTest::test_owner_dashboard_shows_live_figures`
  asserts "Today at the desk" and "Pending requests"; it must be updated with the overview.

## Approaches considered

**Notifications**
- **A. Laravel database notifications + bell that polls every 30 s (chosen).** Standard
  `notifications` table, per-person read state, easy to test with `Notification::fake()`, no extra server.
- B. Same bell + email copies — rejected by the user (noisier; dev email only goes to the log).
- C. Real-time websockets (Laravel Reverb) — rejected: an extra always-running process on a
  low-RAM laptop, and many shared hosts can't run it.
- D. Build the feed from `booking_status_changes` + `payments` with a per-user "seen up to"
  time — rejected: coarse read state, and it can't carry events those tables don't hold
  (wrong-amount payments, chatbot gaps). It is reused for **Recent activity**.

**Admin scope**
- **Console + overview (chosen).** Every console page inherits the new frame.
- Polish the current top bar — rejected (keeps the 7-link bar and the phone hamburger).
- Full page-by-page redesign — not now (largest job; pages still benefit from the frame).

## Design

### 1. Notifications (server side)

- **Table:** Laravel's standard notifications table (`php artisan make:notifications-table`,
  then `migrate` on the dev DB). `User` already uses `Notifiable`.
- **Classes** in `app/Notifications/Console/`, sharing an abstract `ConsoleNotification`
  (`via()` → `['database']`; `toArray()` → one fixed shape:
  `kind, title, body, reference, booking_id|inquiry_id, count`). Links are built when shown,
  from the stored ids, so routes can change later.

| Class | Sent from (after the transaction commits) | To | kind |
|---|---|---|---|
| `NewBookingRequest` | `BookingService::create` | active staff + owner | `booking.requested` |
| `OnlinePaymentReceived` | `PaymentService::handleWebhook` → `paid` | active staff + owner | `payment.received` |
| `PaymentNeedsReview` | `PaymentService::handleWebhook` → `mismatch` | active staff + owner | `payment.review` |
| `GuestCancelledBooking` | `BookingLifecycleService::cancel` when the actor is the guest | active staff + owner | `booking.cancelled` |
| `MoneyRecordedByStaff` | `PaymentService::recordCash` / `recordRefund` | owner only, never the person who recorded it | `payment.cash` / `payment.refund` |
| `ChatbotUnanswered` | `ChatbotService::ask` when `was_fallback` | owner only, batched (below) | `chatbot.unanswered` |

- **`App\Services\ConsoleNotifier`** owns recipients and safety: active `staff` + `admin` users
  (or `admin` only), minus the actor; `Notification::send(...)` inside `try/catch` that logs and
  never throws, the same as `notifyGuest()`. Injected into the four services.
- Duplicate/replayed webhooks return `duplicate` and send nothing. Failed payments send nothing.
- **Chatbot batching:** at most one *unread* `ChatbotUnanswered` per owner per day. A later miss
  deletes that unread one and sends a fresh one with `count + 1`
  ("3 questions today the assistant couldn't answer"), so it rises to the top without reordering
  read items.
- **Cleanup (privacy):** a daily scheduled job deletes *read* notifications older than 90 days.

### 2. Notification center (routes, controller, bell)

Routes inside the existing `role:staff,admin` group (`/staff/...`, names `staff.notifications.*`):

| Route | Purpose |
|---|---|
| `GET /staff/notifications` | Full list, 20 per page, Unread / All tabs |
| `GET /staff/notifications/poll` | JSON `{unread, newest}`; throttled; cheap count query |
| `GET /staff/notifications/panel` | HTML partial of the latest 8, loaded when the dropdown opens |
| `GET /staff/notifications/{id}/open` | Marks it read (own notifications only, else 404) and redirects to its booking / chatbot log |
| `POST /staff/notifications/read-all` | Marks all of *your* unread as read |

- Thin `Staff\NotificationController`; a small presenter maps each notification to icon, tone,
  title, body, relative time and link. Always queries `$request->user()->notifications()`.
- **Bell** (desktop top bar): unread badge (lagoon, not coral), dropdown with header + "Mark all
  read", latest 8 (unread in bold with a dot), "See all" footer, empty state "You're all caught up".
- **Polling** (Alpine component registered in `resources/js/app.js`): every 30 s while the tab is
  visible; checks right away when the tab becomes visible again; **stops after 30 minutes without
  mouse/keyboard input** so an unattended screen can still time out; stops on 401/419.
  New items → bell rings once (skipped with reduced motion), `aria-live` "2 new notifications",
  tab title gets "(2) ".

### 3. Console layout (staff + owner)

- `App\View\Components\AppLayout::render()` returns `layouts.console` for staff/owner and
  `layouts.app` for guests. Every console page already uses `<x-app-layout>`, so pages don't change.
- A view composer builds the role's link groups and badge counts (pending requests, unread
  notifications, owner: unanswered chatbot questions in 7 days).
- **Desktop (lg+):** fixed 16rem sidebar in lagoon ink with the faint graticule texture;
  groups **Today** (Front desk, Bookings), **Manage** (Cottages, Staff), **Insights** (Reports,
  Chatbot log); Overview on top for the owner; account chip at the bottom (initials, name,
  "Owner"/"Front desk", Profile, Log out). Staff see only Today.
- **Top bar** (sticky): "Find a booking — reference, guest name or email" (submits to the queue
  search), bell.
- **Phones (< lg):** slim top bar (brand + search) and a bottom tab bar —
  owner: Overview · Bookings · Alerts · More; staff: Front desk · Bookings · Alerts · More.
  "More" opens a bottom sheet with the remaining links, Profile and Log out. Safe-area padding.
- Kept: skip link, `<main id="main">`, `<x-flash />`, page `$header` slots, print hides the chrome.
- `layouts/navigation.blade.php` keeps serving guests only (its staff/owner branches are removed).
- The Owner chip stops using the coral "rejected" badge style.

```
Desktop                                     Phone
+-----------+-----------------------------+  +---------------------------+
| BookEase  | [Find a booking...]     (3) |  | BookEase           [find] |
| Overview  +-----------------------------+  | Resort overview           |
| TODAY     | Resort overview · Nov 2026  |  | NEEDS ATTENTION           |
|  Front    | NEEDS ATTENTION             |  |  * 2 requests waiting     |
|  desk     |  * 2 requests (oldest 26h)  |  |  ...                      |
|  Bookings |  * P3,000 owed, arrives in 2d| |                           |
| MANAGE    |  * 1 GCash amount to review |  +---------------------------+
|  Cottages | Occupancy | Collected | Owed|  | Overview Bookings Alerts More|
|  Staff    | ._-=#=-_. last 30 days      |  +---------------------------+
| INSIGHTS  | RECENT ACTIVITY             |
|  Reports  |  10:42 GCash P1,500 LBN-... |
|  Chatbot  |                             |
| (KM) Owner|                             |
+-----------+-----------------------------+
```

### 4. Owner overview (`admin/dashboard`)

1. Header as today ("Owner console", "Resort overview · {month}", Full reports).
2. **Needs attention** (one row each, hidden when zero; coral only for money owed / warnings):
   - "{n} requests waiting for approval (oldest {age})" → queue, Pending tab.
   - "{n} guests arriving in the next 3 days still owe {₱}" → queue (Confirmed, check-in within
     3 days, balance > 0, bcmath).
   - "{n} confirmed stays ended without a check-in" → up to 3 bookings listed inline, "+n more".
   - "{n} online payments arrived with the wrong amount" → their alerts (count = your unread
     `PaymentNeedsReview`; opening the alert acknowledges it).
   - "{n} questions in 7 days the assistant couldn't answer" → chatbot log.
   - Nothing to show → "Nothing needs you right now" with a compass line drawing.
3. **Key numbers** (this month): Occupancy, Collected, Still owed, New requests — existing data,
   slimmer, tabular figures.
4. **Collected, last 30 days** — existing `<x-report-bars>`.
5. **Recent activity** (latest 10): `booking_status_changes` and settled `payments`, merged by
   time ("Ana approved LBN-SQZGM6", "GCash ₱1,500 received · LBN-SQZGM6",
   "Refund ₱500 by Owner · LBN-…"), each linking to the booking.
6. Removed: "Today at the desk" tiles and the "Manage" cards (the sidebar covers them).
   `<x-module-card>` stays (guest dashboard uses it).

Services: `AttentionService` (needs-attention rows) and `ActivityFeed` (recent activity), reusing
`ReportService::today()` where it already has the numbers.

### 5. Front desk, queue, booking page

- Front desk: keep the four hero tiles (subtle tilt), list rows get the new code style and
  tabular dates, nautical empty states.
- Queue: code style and tabular numbers; layout unchanged.
- Booking page on phones: the "Next step" buttons (Approve, Reject…, Check in, Check out,
  Cancel…) move into a compact sticky bar just above the tab bar. Desktop unchanged.

### 6. Console-wide refinements

- Motion: keep reveals/stagger; 3D tilt only on hero tiles; everything honours reduced motion.
- `.ref-code` style for booking/receipt codes (Hanken Grotesk, tabular figures, wide tracking)
  instead of the system monospace font. No new font download.
- `tabular-nums` on all money and counts in the console.
- `<x-empty-state>` with small inline line drawings (compass, buoy, anchor).

## Testing

- `ConsoleNotificationsTest`: each trigger notifies the right people (active staff + owner; not
  guests; not inactive staff; not the actor); duplicate webhook sends nothing new; mismatch sends
  `PaymentNeedsReview`; staff cancellation sends nothing to staff; chatbot batching (2nd miss →
  one unread with count 2); a throwing notification never blocks a booking or a payment credit.
- `NotificationCenterTest`: guests get 403; you only see and open your own (others → 404); open
  marks read and redirects; read-all only touches yours; poll JSON shape; poll is throttled.
- `AdminConsoleTest`: owner overview shows each Needs-attention row and Recent activity; staff never
  see owner links; guests still get the old top nav and the chatbot widget.
- Update `AdminReportsTest::test_owner_dashboard_shows_live_figures` for the new wording.
- Full `php artisan test` before starting (baseline) and at the end.

## Manual verification

- You run `php artisan serve` in your own terminal (Claude Code stops background servers when
  memory runs low); Vite is already running.
- Guest books → staff bell shows it within 30 s → open → booking page, read state clears.
  Sandbox payment → "payment received". Guest cancels → alert.
- Screens at 375×812 and 390×844 (phone) and 1366×633 (your laptop): sidebar, top bar, bottom
  tabs, More sheet, sticky actions, empty states, reduced motion.

## Build order

1. Baseline test run.
2. Notifications backend (table, classes, notifier, hooks) + tests.
3. Notification center (routes, controller, presenter, bell + polling) + tests.
4. Console layout (component switch, composer, sidebar, top bar, bottom tabs, More sheet).
5. Owner overview (AttentionService, ActivityFeed) + test updates.
6. Front desk, queue, booking page + phone action bar.
7. Refinements, full test run, screenshots.

## Left to the builder

Icons, exact copy, spacing; whether the chatbot log gets an "unanswered only" filter for the
alert link; small layout details of the phone action bar.

## Process notes

- This spans the backend lane (`app/`, `database/`, `routes/`, `tests/`) and the frontend lane.
  It's done in this chat because the backend chat isn't active.
- The working tree already holds ~47 uncommitted UI files plus the cottage rename. Committing those
  first would keep this feature's changes reviewable — only if you ask. No commits otherwise.
