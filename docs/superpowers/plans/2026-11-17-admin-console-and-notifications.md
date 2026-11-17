# Admin Console & Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give staff and the owner in-app alerts (bell + list, checked every 30 s), a sidebar / tab-bar console layout, and an owner overview built around "Needs attention".

**Architecture:** Laravel database notifications, created by one `ConsoleNotifier` service that the existing booking, payment and chatbot services call after their transactions commit. A thin `Staff\NotificationController` serves the list page, a JSON poll and an HTML panel; an Alpine `consoleBell` component polls. `AppLayout` switches staff/owner pages to a new `layouts.console` shell fed by a view composer; the owner overview reads two new read-only services (`AttentionService`, `ActivityFeed`).

**Tech Stack:** PHP 8.2, Laravel 12, MySQL (dev) / in-memory SQLite (tests), Blade, Tailwind CSS 3.4, Alpine.js 3, Vite.

**Spec:** `docs/superpowers/specs/2026-11-17-admin-console-and-notifications-design.md`

## Global Constraints

- No Livewire/Vue/React/Inertia and no new PHP or JS packages.
- Money only through `App\Support\Money` (bcmath strings); display with `Money::format()` (e.g. `₱3,000.00`).
- Notifications use the `database` channel only, are created **after** the surrounding transaction commits, and any failure is caught and `Log::error`-ed — never thrown.
- Recipients: active (`is_active = true`) users with role `staff` or `admin`; owner-only alerts go to `admin`; never guests; never the user who did the action.
- Notification `data` keys are exactly `kind, title, body, reference, count` — no emails, phone numbers or wallet data.
- Console routes live in the existing `['auth', 'verified', 'role:staff,admin']` `/staff` group; owner-only screens stay under `role:admin`.
- The bell polls every 30 s while the tab is visible, stops after 30 min without input, stops on 401/419, and sends `Accept: application/json`.
- Design rules: coral only for warnings and balances owed; lagoon ink for money; Caslon headings / Hanken Grotesk body; existing 2–8px corner scale; no `transition-all`; every animation has a `prefers-reduced-motion` fallback; `short:` for screens under 720px tall.
- Guests keep `layouts.app`, the top navigation and the chatbot widget.
- Commits only after the user says so. "Checkpoint" steps list what to stage; stage paths explicitly (two chats share this working tree).
- `php artisan test` stays green. Note the baseline count in Task 1.
- On this Windows box, ripgrep `glob` filters with folder paths don't match: search a folder by passing it as the path.

## Review Focus

1. A reservation that fails (over capacity, dates just taken) must send no alert. → Task 1 `test_a_failed_reservation_sends_no_alert`
2. Opening an alert whose booking no longer exists returns to the queue with "That booking is no longer available." instead of a 404. → Task 5 `test_opening_an_alert_for_a_missing_booking_returns_to_the_queue`
3. A session that expired while a console tab polls gets JSON 401 (so the bell stops), never the login page HTML. → Task 5 `test_an_expired_session_gets_json_401_from_poll`
4. More than nine unread: badge reads "9+", dropdown shows 8, list paginates. → Task 5 `test_the_panel_shows_the_latest_eight_and_poll_counts_all`, Task 6 `test_the_bell_caps_its_badge_at_nine`
5. Chatbot misses on either side of midnight start separate alerts. → Task 3 `test_a_miss_after_midnight_starts_a_new_alert`

---

### Task 1: Alert storage, notifier, new-request alerts

**Files:**
- Create: `database/migrations/<timestamp>_create_notifications_table.php` (via artisan)
- Create: `app/Notifications/Console/ConsoleNotification.php`, `app/Notifications/Console/NewBookingRequest.php`
- Create: `app/Services/ConsoleNotifier.php`
- Modify: `app/Services/BookingService.php` (constructor line 24; `create()` lines 32-75)
- Test: `tests/Feature/BookingAlertsTest.php`

**Interfaces:**
- Produces: `abstract class ConsoleNotification extends Illuminate\Notifications\Notification` with `abstract kind(): string`, `abstract title(): string`, `abstract body(): string`, `reference(): ?string` (default `null`), `count(): int` (default `1`), `final via(object $n): array` → `['database']`, `final toArray(object $n): array` → `['kind','title','body','reference','count']` in that order.
- Produces: `NewBookingRequest(Booking $booking)` — kind `booking.requested`, title `New booking request`, body `"{guest name} · {cottage name} · {check_in M j}–{check_out M j}"`, reference = `reference_code`.
- Produces: `ConsoleNotifier::bookingRequested(Booking $booking): void`; private `desk(?User $except = null): Collection` (active staff + admin), `owners(?User $except = null): Collection` (active admin), `send(Collection $to, ConsoleNotification $n): void`.

- [ ] **Step 1: Baseline** — Run `php artisan test`. Note "Tests: N passed" (the handoff reported 363).

- [ ] **Step 2: Write the failing tests**

```php
class BookingAlertsTest extends TestCase
{
    use RefreshDatabase;
    // setUp: travelTo('2026-11-01 09:00:00'); guest = User::factory()->create(['name' => 'Ana Reyes']);
    // staff = ->staff(); owner = ->admin(); cottage = Cottage::factory()->create(['name' => 'Seaside Cottage A', 'capacity' => 4]);
    // book(array $o = []): actingAs(guest)->post("/cottages/{$cottage->id}/book", array_merge(['check_in' => '2026-11-24',
    //   'check_out' => '2026-11-26', 'guests_count' => 2, 'phone' => '+63 917-123-4567', 'agree' => '1'], $o))

    public function test_a_new_request_alerts_active_staff_and_the_owner(): void
    {
        Notification::fake();
        $inactive = User::factory()->staff()->inactive()->create();
        $this->book()->assertRedirect();
        Notification::assertSentTo([$this->staff, $this->owner], NewBookingRequest::class);
        Notification::assertNotSentTo([$this->guest, $inactive], NewBookingRequest::class);
    }

    public function test_the_alert_stores_only_what_the_console_shows(): void
    {
        $this->book();
        $data = $this->staff->notifications()->sole()->data;
        $this->assertSame(['kind', 'title', 'body', 'reference', 'count'], array_keys($data));
        $this->assertSame('booking.requested', $data['kind']);
        $this->assertSame(Booking::sole()->reference_code, $data['reference']);
        $this->assertSame('Ana Reyes · Seaside Cottage A · Nov 24–Nov 26', $data['body']);
        $this->assertStringNotContainsString($this->guest->email, json_encode($data));
    }

    public function test_a_failed_reservation_sends_no_alert(): void
    {
        Notification::fake();
        $this->book(['guests_count' => 5])->assertSessionHasErrors('guests_count');
        Booking::factory()->for($this->cottage)->stay('2026-11-24', '2026-11-26')->status(BookingStatus::Confirmed)->create();
        $this->book(); // dates now taken
        Notification::assertNothingSent();
    }

    public function test_an_alert_failure_never_blocks_a_booking(): void
    {
        Notification::shouldReceive('send')->andThrow(new RuntimeException('notifications table is missing'));
        $this->book()->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Booking::count());
    }
}
```

- [ ] **Step 3: Run to see it fail** — `php artisan test --filter=BookingAlertsTest` → FAIL, class `NewBookingRequest` not found.
- [ ] **Step 4: Create the table** — `php artisan make:notifications-table`, then `php artisan migrate` (dev DB).
- [ ] **Step 5: Implement** `ConsoleNotification`, `NewBookingRequest` and `ConsoleNotifier`. `send()` returns early on an empty collection, calls the `Notification::send($to, $n)` facade (so tests can make it throw), and catches `Throwable` → `Log::error('Could not create a console notification', ['kind' => $n->kind(), 'error' => $e->getMessage()])`.
- [ ] **Step 6: Hook** — Inject `ConsoleNotifier $notifier` into `BookingService`. In `create()`, keep the `reserve()` result, call `$this->notifier->bookingRequested($booking->loadMissing(['user', 'cottage']))`, then return it. This runs outside `reserve()`'s transaction.
- [ ] **Step 7: Run** — `php artisan test --filter="BookingAlertsTest|ReservationTest"` → PASS.
- [ ] **Step 8: Checkpoint** — stage `app/Notifications/Console app/Services/ConsoleNotifier.php app/Services/BookingService.php database/migrations/*notifications* tests/Feature/BookingAlertsTest.php`; message `feat: alert staff and owner about new booking requests`.

### Task 2: Payment alerts

**Files:**
- Create: `app/Notifications/Console/OnlinePaymentReceived.php`, `PaymentNeedsReview.php`, `MoneyRecordedByStaff.php`
- Modify: `app/Services/ConsoleNotifier.php`; `app/Services/PaymentService.php` (constructor 36-39; `handleWebhook` 197-203; `recordCash` 243-248; `recordRefund` 256-272)
- Test: `tests/Feature/PaymentAlertsTest.php`

**Interfaces:**
- Consumes: `ConsoleNotification`, `ConsoleNotifier::desk/owners/send` (Task 1).
- Produces: `OnlinePaymentReceived(Payment $p)` — kind `payment.received`, title `"{method label} payment received"`, body `"{Money::format(amount)} {kind label, lower-case} · {booking ref}"`.
- Produces: `PaymentNeedsReview(Payment $p)` — kind `payment.review`, title `Payment needs review`, body `"A {method label} payment for {ref} arrived with a different amount than the {Money::format(amount)} requested."`
- Produces: `MoneyRecordedByStaff(Payment $p)` — kind `payment.refund` if `$p->isRefund()` else `payment.cash`; title `Refund recorded` / `Cash payment recorded`; body `"{Money::format(amount)} by {recordedBy name} · {ref}"`.
- Produces: `ConsoleNotifier::paymentReceived(Payment)`, `paymentNeedsReview(Payment)` (desk), `moneyRecorded(Payment $p, User $actor)` (owners minus actor). All three classes set `reference()` to the booking reference.

- [ ] **Step 1: Write the failing tests.** In `setUp`, copy `PaymentFlowTest`: lines 34-47 (fake driver, 50% down payment, `Notification::fake()`, booking total `'7500.75'`) and helpers 49-72 (`gateway()`, `startCheckout()`, `sendWebhook()`). Also add `staff` and `owner`, and `deskUrl($a)` = `"/staff/bookings/{$ref}/{$a}"`.

```php
public function test_a_signed_online_payment_alerts_the_desk_once(): void
{
    $webhook = $this->gateway()->makeWebhook($this->startCheckout());
    $this->sendWebhook($webhook)->assertJson(['outcome' => 'paid']);
    $this->sendWebhook($webhook)->assertJson(['outcome' => 'duplicate']);
    Notification::assertSentToTimes($this->staff, OnlinePaymentReceived::class, 1);
    Notification::assertSentToTimes($this->owner, OnlinePaymentReceived::class, 1);
    Notification::assertSentTo($this->staff, OnlinePaymentReceived::class, fn ($n) => $n->title() === 'GCash payment received'
        && $n->body() === '₱3,750.38 down payment · '.$this->booking->reference_code);
}
public function test_a_wrong_amount_payment_asks_the_desk_to_review(): void
{   // makeWebhook($payment, amountCentavos: 100) → outcome 'mismatch'
    // assertSentTo([staff, owner], PaymentNeedsReview::class); assertNotSentTo([staff, owner], OnlinePaymentReceived::class)
}
public function test_a_failed_online_payment_sends_no_alert(): void
{   // makeWebhook($payment, paid: false) → 'failed'; neither class sent to staff or owner
}
public function test_cash_from_staff_alerts_the_owner_only(): void
{
    $other = User::factory()->staff()->create();
    $this->actingAs($this->staff)->post($this->deskUrl('cash'), ['amount' => '3000'])->assertSessionHas('status');
    Notification::assertSentTo($this->owner, MoneyRecordedByStaff::class, fn ($n) => $n->kind() === 'payment.cash'
        && $n->body() === '₱3,000.00 by '.$this->staff->name.' · '.$this->booking->reference_code);
    Notification::assertNotSentTo([$this->staff, $other], MoneyRecordedByStaff::class);
}
public function test_the_owner_gets_no_alert_for_their_own_refund(): void
{
    $partner = User::factory()->admin()->create();
    $this->actingAs($this->owner)->post($this->deskUrl('cash'), ['amount' => '3000']);
    $this->actingAs($this->owner)->post($this->deskUrl('refund'), ['refund_amount' => '1000', 'refund_method' => 'cash']);
    Notification::assertNotSentTo($this->owner, MoneyRecordedByStaff::class);
    Notification::assertSentTo($partner, MoneyRecordedByStaff::class, fn ($n) => $n->kind() === 'payment.refund');
}
public function test_an_alert_failure_never_undoes_a_credited_payment(): void
{
    $payment = $this->startCheckout();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('down'));
    $this->sendWebhook($this->gateway()->makeWebhook($payment))->assertJson(['outcome' => 'paid']);
    $this->assertSame('3750.38', $this->booking->fresh()->amount_paid);
}
```

- [ ] **Step 2: Run** — `php artisan test --filter=PaymentAlertsTest` → FAIL (classes missing).
- [ ] **Step 3: Implement** the three classes and three notifier methods.
- [ ] **Step 4: Hook (all after the transaction):**
  - `handleWebhook`: on `paid`, keep `notifyGuest()` and call `paymentReceived()`; on `mismatch`, call `paymentNeedsReview()`.
  - `recordCash`: call `moneyRecorded($payment, $staff)` after `notifyGuest()`.
  - `recordRefund`: assign the transaction's result to `$payment`, call `moneyRecorded($payment, $staff)`, then return it.
- [ ] **Step 5: Run** — `php artisan test --filter="PaymentAlertsTest|PaymentFlowTest|StaffPaymentTest|SupersededCheckoutTest"` → PASS.
- [ ] **Step 6: Checkpoint** — stage the new classes, `ConsoleNotifier.php`, `PaymentService.php`, `PaymentAlertsTest.php`; `feat: alert the desk about online payments and the owner about cash and refunds`.

### Task 3: Guest-cancellation and chatbot-gap alerts

**Files:**
- Create: `app/Notifications/Console/GuestCancelledBooking.php`, `ChatbotUnanswered.php`
- Modify: `app/Services/ConsoleNotifier.php`; `app/Services/BookingLifecycleService.php` (constructor 25; `cancel()` 67-68); `app/Services/ChatbotService.php` (constructor 43-47; after `ChatInquiry::create` 86-93)
- Test: `tests/Feature/BookingAlertsTest.php` (add), `tests/Feature/ChatbotAlertsTest.php`

**Interfaces:**
- Produces: `GuestCancelledBooking(Booking $b)` — kind `booking.cancelled`, title `Guest cancelled a booking`, body `"{guest} · {cottage} · {M j}–{M j}. The dates are open again."`, reference.
- Produces: `ChatbotUnanswered(int $count)` — kind `chatbot.unanswered`, title `Questions the assistant couldn't answer`, body `"{count} {question|questions} today the assistant couldn't answer"`, reference `null`, `count()` = `$count`.
- Produces: `ConsoleNotifier::guestCancelled(Booking $b): void` (desk) and `chatbotMissed(): void`. For each owner, find the unread `ChatbotUnanswered` created since `today()` (app timezone), delete it, and send a new one with count = its count + 1, or 1 if none. The whole method is wrapped in `try/catch`.

- [ ] **Step 1: Write the failing tests.**
  - `BookingAlertsTest::test_a_guest_cancellation_alerts_the_desk` — a Confirmed booking for the guest (Nov 24–26). The guest POSTs `/my/bookings/{ref}/cancel` → `assertSentTo([staff, owner], GuestCancelledBooking::class)`, and nothing is sent to the guest.
  - `test_a_cancellation_by_staff_sends_no_desk_alert` — staff POSTs `/staff/bookings/{ref}/cancel` with `['remarks' => 'Typhoon signal no. 2.']` → `assertNotSentTo([staff, owner], GuestCancelledBooking::class)`.
  - `ChatbotAlertsTest`:
    - `setUp`: `travelTo('2026-11-01 09:00:00')`, then `config(['bookease.chatbot.enabled' => false, 'bookease.contact' => ['phone' => null, 'email' => null, 'facebook' => null]])`. Disabling the chatbot makes every answer a fallback with no HTTP call. Also create `owner` and `staff`.
    - `ask()` = `postJson('/chatbot', ['question' => 'Do you allow pets?'])`.
    - Do **not** call `Http::fake()` in `setUp`: a catch-all stub would shadow the one used in the answered-question test.

```php
public function test_an_unanswered_question_alerts_the_owner_only(): void
{
    $this->ask()->assertJson(['fallback' => true]);
    $this->assertSame('chatbot.unanswered', $this->owner->unreadNotifications()->sole()->data['kind']);
    $this->assertSame(0, $this->staff->notifications()->count());
}
public function test_misses_on_the_same_day_share_one_alert(): void
{
    $this->ask(); $this->ask();
    $alert = $this->owner->unreadNotifications()->sole();
    $this->assertSame(2, $alert->data['count']);
    $this->assertSame("2 questions today the assistant couldn't answer", $alert->data['body']);
}
public function test_a_miss_after_midnight_starts_a_new_alert(): void
{
    $this->travelTo('2026-11-01 23:50:00'); $this->ask();
    $this->travelTo('2026-11-02 00:10:00'); $this->ask();
    $this->assertSame([1, 1], $this->owner->unreadNotifications()->get()->pluck('data.count')->all());
}
public function test_a_read_alert_is_not_reused(): void
{
    $this->ask(); $this->owner->unreadNotifications->markAsRead(); $this->ask();
    $this->assertSame(1, $this->owner->unreadNotifications()->sole()->data['count']);
}
public function test_an_answered_question_sends_no_alert(): void
{
    config(['bookease.chatbot.enabled' => true, 'services.gemini.api_key' => 'test-gemini-key', 'services.gemini.model' => 'gemini-test-flash']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [[
        'content' => ['role' => 'model', 'parts' => [['text' => 'Check-in is at 2:00 PM.']]], 'finishReason' => 'STOP']]])]);
    $this->postJson('/chatbot', ['question' => 'What time is check-in?'])->assertJson(['fallback' => false]);
    $this->assertSame(0, $this->owner->notifications()->count());
}
```

- [ ] **Step 2: Run** — `php artisan test --filter="BookingAlertsTest|ChatbotAlertsTest"` → FAIL.
- [ ] **Step 3: Implement** the two classes and two notifier methods.
- [ ] **Step 4: Hook**
  - `BookingLifecycleService::cancel()`: when `$byGuest`, call `guestCancelled($booking->loadMissing(['user', 'cottage']))`, then return. Otherwise keep `notifyGuest()`.
  - `ChatbotService::ask()`: after the inquiry is logged, `if ($fallback) { $this->notifier->chatbotMissed(); }`.
- [ ] **Step 5: Run** — `php artisan test --filter="BookingAlertsTest|ChatbotAlertsTest|ChatbotTest|ChatbotGuardrailTest|GeminiFallbackTest|BookingLifecycleTest|BookingStatusEmailTest"` → PASS.
- [ ] **Step 6: Checkpoint** — `feat: alert the desk about guest cancellations and the owner about chatbot gaps`.

### Task 4: Delete old read alerts

**Files:**
- Modify: `routes/console.php`
- Test: `tests/Feature/NotificationPruneTest.php`

**Interfaces:**
- Produces: the Artisan command `notifications:prune-read`. It deletes notifications whose `read_at` is older than 90 days and prints `Deleted {n} read notifications older than 90 days.` It is scheduled with `Schedule::command('notifications:prune-read')->daily();`.

- [ ] **Step 1: Write the failing test** `test_read_alerts_older_than_90_days_are_deleted`. Give a staff user three rows via `$staff->notifications()->create([...])` (uuid `id`, `type` = `ChatbotUnanswered::class`, a valid `data` array):
  - read 91 days ago;
  - read 89 days ago;
  - unread, with `created_at` 200 days ago.

  Run `$this->artisan('notifications:prune-read')->assertSuccessful()`. Expect 2 rows left: the 89-day read one and the unread one.
- [ ] **Step 2: Run** → FAIL (command not defined).
- [ ] **Step 3: Implement** the command in `routes/console.php` with `Illuminate\Notifications\DatabaseNotification::query()->whereNotNull('read_at')->where('read_at', '<', now()->subDays(90))->delete()`, plus the schedule line.
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Checkpoint** — `feat: prune read console notifications after 90 days`.

### Task 5: Notification center (list, poll, panel, open)

**Files:**
- Create: `app/Support/ConsoleAlert.php`
- Create: `app/Http/Controllers/Staff/NotificationController.php`
- Create: `resources/views/staff/notifications/index.blade.php`, `_items.blade.php`, `_panel.blade.php`
- Create: `resources/views/components/empty-state.blade.php`
- Modify: `routes/web.php` (staff group, after line 120)
- Test: `tests/Feature/NotificationCenterTest.php`

**Interfaces:**
- Consumes: the notification classes from Tasks 1–3.
- Produces: `final class ConsoleAlert` with readonly fields `string $id, string $kind, string $title, string $body, ?string $reference, int $count, bool $read, CarbonImmutable $at, string $tone, string $targetUrl`, and `static from(DatabaseNotification $n): self`.
  - Tone by kind:
    - `booking.requested` → `attention`
    - `booking.cancelled` and `chatbot.unanswered` → `info`
    - `payment.received`, `payment.cash` and `payment.refund` → `money`
    - `payment.review` → `warning`
  - `targetUrl`:
    - when there is a reference → `route('staff.bookings.show', $reference)`
    - `chatbot.unanswered` → `route('admin.chatbot.index', ['filter' => 'fallback'])`
    - otherwise → `route('staff.notifications.index')`
- Produces these routes (names under `staff.`):

| Method | Path | Action | Name | Middleware |
|---|---|---|---|---|
| GET | `/notifications` | `index` | `notifications.index` | – |
| GET | `/notifications/poll` | `poll` | `notifications.poll` | `throttle:30,1` |
| GET | `/notifications/panel` | `panel` | `notifications.panel` | `throttle:30,1` |
| GET | `/notifications/{notification}/open` | `open` | `notifications.open` | – |
| POST | `/notifications/read-all` | `readAll` | `notifications.read-all` | – |

- Produces these controller methods. All of them query `$request->user()->notifications()` only.
  - `index(Request): View` — `?filter=all` shows everything, the default shows unread; 20 per page, `withQueryString()`.
  - `poll(Request): JsonResponse` — `{"unread": int, "newest": ?string}`, where `newest` is the id of the newest unread notification.
  - `panel(Request): View` — the 8 newest, read or unread.
  - `open(Request, string $notification): RedirectResponse` — `findOrFail`, then `markAsRead()`. If its reference matches no `reference_code`, redirect to `staff.bookings.index` with status `That booking is no longer available.`; otherwise redirect to `targetUrl`.
  - `readAll(Request): RedirectResponse` — marks your unread as read, then goes back with status `All caught up.`
- Produces: `<x-empty-state icon="compass|buoy|anchor">message</x-empty-state>` — a small inline SVG line drawing in lagoon ink at 40% opacity, with the message underneath.
- Produces these view details:
  - Each item renders as `<li data-alert>` linking to `route('staff.notifications.open', $alert->id)`.
  - Unread items are `font-semibold` with a lagoon dot. The tone icon is coral only for `warning`/`money`. Times use `diffForHumans()`.
  - The panel has the header "Notifications", a "Mark all read" form, the items and a "See all" link. When empty it shows `<x-empty-state icon="compass">You're all caught up.</x-empty-state>`.

- [ ] **Step 1: Write the failing tests.**
  - `setUp`: `travelTo('2026-11-01 09:00:00')`, then create `staff`, `otherStaff`, `owner` and `guest`, and a booking.
  - Helper `alert(User $to, ?Booking $b = null)` = `$to->notify(new NewBookingRequest($b ?? $this->booking))`.
  - Call `$this->travel(1)->minutes()` between alerts so the order is clear.
  - The tests:
    - `test_guests_cannot_use_the_notification_center` — `get('/staff/notifications')` → 403; `getJson('/staff/notifications/poll')` → 403.
    - `test_staff_see_only_their_own_alerts` — the index shows your booking's reference and not the other staff member's.
    - `test_poll_reports_the_unread_count_and_newest_alert` — `assertExactJson(['unread' => 2, 'newest' => $latest->id])`.
    - `test_opening_an_alert_marks_it_read_and_goes_to_the_booking` — `assertRedirect(route('staff.bookings.show', $booking))`, and `read_at` is now set.
    - `test_opening_someone_elses_alert_is_not_found` — 404.
    - `test_opening_an_alert_for_a_missing_booking_returns_to_the_queue` — alert, then `$booking->delete()`, then open → `assertRedirect(route('staff.bookings.index'))->assertSessionHas('status', 'That booking is no longer available.')`.
    - `test_mark_all_read_only_touches_your_own` — the other staff member's alert stays unread.
    - `test_the_panel_shows_the_latest_eight_and_poll_counts_all` — 12 alerts → poll `unread` = 12; `substr_count($panel->getContent(), 'data-alert') === 8`.
    - `test_an_expired_session_gets_json_401_from_poll` — signed out: `getJson('/staff/notifications/poll')->assertUnauthorized()`.
    - `test_poll_is_rate_limited` — 30 polls return 200, the 31st returns 429.
- [ ] **Step 2: Run** — `php artisan test --filter=NotificationCenterTest` → FAIL (404s).
- [ ] **Step 3: Implement** the routes, `ConsoleAlert`, the controller, the three views and `x-empty-state`. The index page uses `<x-app-layout>` with `x-page-header title="Notifications"`, Unread/All tabs styled like the queue tabs, and pagination.
- [ ] **Step 4: Run** → PASS, then `php artisan test` (full) → PASS.
- [ ] **Step 5: Checkpoint** — `feat: notification center for staff and the owner`.

### Task 6: Console layout (sidebar, top bar, tab bar)

**Files:**
- Create: `resources/views/layouts/console.blade.php`
- Create: `resources/views/layouts/partials/console-sidebar.blade.php`, `console-topbar.blade.php`, `console-tabbar.blade.php`, `console-bell.blade.php`
- Create: `app/View/Composers/ConsoleComposer.php`
- Modify: `app/View/Components/AppLayout.php:13-16`; `app/Providers/AppServiceProvider.php` (`boot`)
- Modify: `resources/views/layouts/navigation.blade.php` (drop the Admin/Staff arms and `$wide`; guests only)
- Modify: `resources/css/app.css` (components layer)
- Test: `tests/Feature/ConsoleLayoutTest.php`

**Interfaces:**
- Consumes: `staff.notifications.*` routes (Task 5).
- Produces: `AppLayout::render()` → `view(auth()->user()?->isGuest() === false ? 'layouts.console' : 'layouts.app')`.
- Produces: `ConsoleComposer::compose(View $view): void`, registered with `View::composer('layouts.console', ConsoleComposer::class)`. It shares `$console` with these keys:
  - `home`: `admin.dashboard` for the owner, `staff.dashboard` for staff.
  - `roleLabel`: `Owner` or `Front desk`.
  - `pending`: the count of `Pending` bookings.
  - `unread`: the user's unread count.
  - `groups`: a list of `['label' => ?string, 'links' => list<['route', 'label', 'pattern', 'icon', 'badge' => ?int]>]`.
    - Owner: `[null: Overview]`, `[Today: Front desk, Bookings(badge pending)]`, `[Manage: Cottages, Staff]`, `[Insights: Reports, Chatbot log(badge = fallbacks in the last 7 days)]`.
    - Staff: `[Today: Front desk, Bookings(badge pending)]` only.
  - `tabs`:
    - Owner: `Overview · Bookings · Alerts · More`.
    - Staff: `Front desk · Bookings · Alerts · More`.
  - `more`:
    - Owner: `Front desk, Cottages, Staff, Reports, Chatbot log`.
    - Staff: none.
    - Both always also get Profile and Log out.
- Produces these test hooks:
  - the sidebar root has `data-console-sidebar`;
  - the tab bar root has `data-console-tabbar`;
  - the Bookings link has `data-pending-count="{n}"`;
  - the bell has `data-unread="{n}"` and shows `9+` when n > 9, hidden at 0.
- Layout values:
  - **Shell:**
    - The sidebar is `hidden lg:flex fixed inset-y-0 left-0 w-64` and the content column `lg:pl-64`.
    - The top bar is `sticky top-0 z-30 h-16` with `bg-cream/90 backdrop-blur`.
    - The tab bar is `lg:hidden fixed inset-x-0 bottom-0` (`h-14` plus `env(safe-area-inset-bottom)`), and `<main>` gets `pb-[calc(4.5rem+env(safe-area-inset-bottom))] lg:pb-0`.
    - The sidebar, top bar and tab bar are all `print:hidden`.
    - The skip link, `<x-flash />`, `<main id="main">` and the `$header`/`$slot` wrappers are copied from `layouts/app.blade.php:7-28`.
  - **Sidebar:**
    - `bg-lagoon-950 text-cream/80`, with a `.graticule` overlay at about 6% opacity.
    - `<x-brand light>` at the top.
    - Group labels are `text-[0.65rem] uppercase tracking-[0.2em] text-cream/45`.
    - The account chip at the bottom shows initials, name and `roleLabel`, with Profile and Log out.
  - **CSS classes:**
    - `.console-link`: flex, `gap-3`, `rounded-md`, `px-3 py-2`, `text-sm`, hover `bg-cream/5`, transitions only `background-color, color`.
    - `.console-link.is-active`: `bg-cream text-lagoon-950`.
    - `.console-badge`: `rounded-md bg-sand-200 px-1.5 text-xs tabular-nums text-lagoon-950`.
  - **Top bar:**
    - The search is a GET form to `staff.bookings.index` with hidden `tab=all` and field `q`, styled `.field` with a search icon. Placeholder: "Find a booking — reference, guest name or email".
    - On phones the top bar shows the brand plus a search icon that toggles the search row.
    - The bell partial is a link to `staff.notifications.index` for now; Task 7 makes it a dropdown.
  - **More sheet:**
    - An Alpine `moreOpen` flag; the sheet is `fixed inset-x-0 bottom-0 rounded-t-3xl bg-cream shadow-plate` over a `bg-lagoon-950/40` backdrop.
    - It opens with a transform + opacity transition (300 ms, `ease-out-soft`) and closes on Escape.
    - The Owner chip is a lagoon `badge`, not `badge-rejected`.

- [ ] **Step 1: Write the failing tests.**
  - `test_staff_pages_use_the_console_layout` — `get('/staff')` shows `data-console-sidebar` and `Find a booking`.
  - `test_staff_see_only_their_desk_links` — `assertDontSee(route('admin.reports.index'))->assertDontSee('Insights')`.
  - `test_the_owner_sees_every_group_in_order` — `get('/admin')->assertSeeInOrder(['Overview', 'Today', 'Front desk', 'Bookings', 'Manage', 'Cottages', 'Staff', 'Insights', 'Reports', 'Chatbot log'])`.
  - `test_the_bookings_link_shows_the_pending_count` — with 2 pending bookings, `assertSee('data-pending-count="2"', false)`.
  - `test_the_bell_caps_its_badge_at_nine` — with 12 alerts for staff, `assertSee('data-unread="12"', false)->assertSee('9+')`.
  - `test_phones_get_the_owner_tabs` — `assertSeeInOrder(['data-console-tabbar', 'Overview', 'Bookings', 'Alerts', 'More'], false)`.
  - `test_guests_keep_the_top_navigation` — `get('/my')` shows `My stays` and no `data-console-sidebar`.
  - `test_staff_profile_uses_the_console` — `get('/profile')` shows `data-console-sidebar`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** the composer, the layout and partials, the `AppLayout` switch, the guest-only `navigation.blade.php`, and the CSS.
- [ ] **Step 4: Run** `php artisan test` (full) → PASS. Expect to fix any text-asserting tests the new chrome breaks, like `AdminReportsTest` (Task 8 also changes it).
- [ ] **Step 5: Look at it** — open `/admin` and `/staff` at 1366×633 and 390×844. The sidebar must fit or scroll inside itself, there must be no horizontal page scroll, and the tab bar must not cover content.
- [ ] **Step 6: Checkpoint** — `feat: console layout with sidebar, search and phone tab bar`.

### Task 7: Live bell (dropdown + 30 s polling)

**Files:**
- Create: `resources/js/console-bell.js`
- Modify: `resources/js/app.js:4-8` (import + `Alpine.data('consoleBell', consoleBell)`); `resources/views/layouts/partials/console-bell.blade.php`; `console-tabbar.blade.php` (Alerts badge); `resources/css/app.css` (`.bell-ring`)
- Test: `tests/Feature/ConsoleLayoutTest.php` (add)

**Interfaces:**
- Consumes: `staff.notifications.poll`, `.panel`, `.index` (Task 5); `data-unread` markup (Task 6).
- Produces: `export default function consoleBell({ pollUrl, panelUrl, unread, interval = 30000, idleLimit = 1800000 })`, which returns Alpine data `{ unread, newest, open, panelHtml, init(), poll(), toggle(), loadPanel(), stop() }`.
- Behaviour (pinned by the spec):
  - **Polling:**
    - Poll on a `setTimeout` chain only while `document.visibilityState === 'visible'`, and poll right away on `visibilitychange` back to visible.
    - Track the last `pointerdown`/`keydown`/`scroll`/`touchstart` (passive listeners). After 30 min with no input, stop until the next input, then poll and resume.
    - Fetch with `{ credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }`. A 401 or 419 stops polling for good.
  - **When unread goes up:**
    - Add `.bell-ring` to `$refs.bell` and remove it on `animationend`; skip this under reduced motion.
    - Write "`{n} new notification(s)`" into an `aria-live="polite"` sr-only region.
    - Set the `document.title` prefix to `({unread}) ` (replacing any old prefix).
    - Dispatch `window` `console-unread` with the count; the tab-bar Alerts badge listens for it.
  - **Dropdown:**
    - Opening it fetches `panelUrl` into `panelHtml` (rendered with `x-html`), and it refetches when a poll finds a new `newest` while open.
    - It closes on outside click or Escape. It is `w-96`, right-aligned, `rounded-2xl shadow-lift`, with a scale + fade transition.
  - **Badge text:** `unread > 9 ? '9+' : String(unread)`, hidden at 0.

- [ ] **Step 1: Write the failing test** `test_the_bell_is_wired_to_the_poll_and_panel_endpoints`: `get('/staff')->assertSee('x-data="consoleBell(', false)->assertSee(route('staff.notifications.poll'), false)->assertSee(route('staff.notifications.panel'), false)`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** `console-bell.js`, the bell partial, the tab-bar listener, and the `.bell-ring` keyframes (a small rotate wobble of about 600 ms; `animation: none` under `prefers-reduced-motion`).
- [ ] **Step 4: Run** `php artisan test --filter=ConsoleLayoutTest` → PASS.
- [ ] **Step 5: Check it in the browser** with Vite running and staff signed in at `/staff`:
  1. In another browser profile, book as a guest. Within 30 s the badge goes 0 → 1, the bell rings once, and the tab title becomes `(1) …`.
  2. Open the dropdown: the item shows. Click it: you land on the booking and the badge returns to 0.
  3. Hide the tab for over 30 s: the DevTools Network tab shows no poll requests while hidden.
  4. Emulate reduced motion: the bell doesn't ring.
- [ ] **Step 6: Checkpoint** — `feat: live notification bell with 30-second polling`.

### Task 8: Owner overview (Needs attention, key numbers, activity)

**Files:**
- Create: `app/Support/AttentionItem.php`, `app/Support/ActivityEntry.php`, `app/Services/AttentionService.php`, `app/Services/ActivityFeed.php`
- Modify: `app/Http/Controllers/Admin/DashboardController.php`, `resources/views/admin/dashboard.blade.php`, `tests/Feature/AdminReportsTest.php:121-134`
- Test: `tests/Feature/OwnerOverviewTest.php`

**Interfaces:**
- Consumes: `PaymentNeedsReview` (Task 2), `x-empty-state` (Task 5), `ReportService::today()`, `build()`, `x-report-bars`.
- Produces: `final class AttentionItem(string $key, string $tone, string $text, string $url, array $bookings = [])`, where `$bookings` holds up to 3 `Booking`s shown inline.
- Produces: `AttentionService::items(User $viewer): array` → `list<AttentionItem>` in this order, leaving out zeros. The copy is exact; singular and plural shown:

| key | tone | Counts | Text | url |
|---|---|---|---|---|
| `pending` | attention | Pending bookings; age of the oldest `created_at` | `1 request waiting for approval (oldest 26h)` / `2 requests … (oldest 3 days)` — `{h}h` under 48 h, else `{floor days} days` | `staff.bookings.index?tab=pending` |
| `due_soon` | money | Confirmed, `check_in` from today to today+3, `balance()` > 0 | `1 guest arriving in the next 3 days still owes ₱3,000.00` / `2 guests … still owe ₱5,000.00` | `?tab=upcoming` (+3 bookings) |
| `no_show` | warning | Confirmed with `check_out` ≤ today | `1 confirmed stay ended without a check-in` / `2 confirmed stays …` | `?tab=arrivals` (+3 bookings) |
| `payment_review` | warning | Viewer's unread notifications of type `PaymentNeedsReview::class` | `1 online payment arrived with the wrong amount` / `2 online payments …` | `staff.notifications.index` |
| `chatbot` | info | `ChatInquiry` with `was_fallback`, last 7 days | `1 question in the last 7 days the assistant couldn't answer` / `3 questions …` | `admin.chatbot.index?filter=fallback` |

- Produces: `final class ActivityEntry(CarbonImmutable $at, string $text, ?string $reference, string $tone)`.
- Produces: `ActivityFeed::latest(int $limit = 10): array` → `list<ActivityEntry>`, newest first. It takes the latest `$limit` rows from each source, merges them, sorts by time and keeps `$limit`.
  - Status changes:
    - from `null` → `"{guest} requested {cottage} · {M j}–{M j}"`
    - to Confirmed / Rejected / Cancelled / CheckedIn / CheckedOut → `"{actor} approved|rejected|cancelled|checked in|checked out {ref}"`
    - when there is no actor, use `System`.
  - Settled payments (by `paid_at`):
    - refund → `"Refund {₱} by {recorder} · {ref}"`
    - cash → `"Cash {₱} recorded by {recorder} · {ref}"`
    - online → `"{Method} {₱} received · {ref}"`
- Controller: add `attention` => `$attention->items($request->user())` and `activity` => `$feed->latest(10)`; drop `staffCount` and `chat`.
- View, in this order:
  1. The existing header.
  2. **Needs attention**: one row per item with a tone dot (coral for money/warning, sand for attention, ocean for info), its text and link, and inline `ref · guest` links. When empty: `<x-empty-state icon="compass">Nothing needs you right now.</x-empty-state>`.
  3. **This month so far**: one card row of Occupancy, Collected, Still owed and New requests, using the existing `$month`/`$today` data with `tabular-nums`. "Still owed" is coral when above zero.
  4. The existing `x-report-bars`.
  5. **Recent activity**: `H:i` for today, otherwise `M j`; the text; a link when there is a reference.

  Remove the "Today at the desk" tiles and the "Manage" cards.

- [ ] **Step 1: Write the failing tests.** `setUp`: `travelTo('2026-11-10 09:00:00')`, and create the owner.
  - `test_waiting_requests_show_the_oldest_age` — a pending booking with `created_at` 26 h ago → `assertSee('1 request waiting for approval (oldest 26h)')`.
  - `test_guests_arriving_soon_with_a_balance_are_listed` — Confirmed, Nov 12–14, `total_price` 3000.00 and nothing paid → `assertSee('1 guest arriving in the next 3 days still owes ₱3,000.00')` and the booking's reference is shown.
  - `test_no_shows_are_listed` — Confirmed, Nov 7–9 → `assertSee('1 confirmed stay ended without a check-in')`.
  - `test_unread_wrong_amount_alerts_are_counted`:
    1. Run `$owner->notify(new PaymentNeedsReview(Payment::factory()->create()))`.
    2. Check `assertSee('1 online payment arrived with the wrong amount')`.
    3. Mark the alert read.
    4. Check `assertDontSee('arrived with the wrong amount')`.
  - `test_unanswered_chatbot_questions_are_counted` — `ChatInquiry::factory()->fallback()->count(3)->create()` → `assertSee("3 questions in the last 7 days the assistant couldn't answer")`.
  - `test_an_all_clear_overview` — `assertSee('Nothing needs you right now')`.
  - `test_recent_activity_lists_decisions_and_payments_newest_first`:
    - Set up three events, using `travelTo` to set each time:
      - 10:00 — the guest's "requested" change;
      - 11:00 — the staff member's approval;
      - 12:00 — a paid GCash `'1000.00'`.
    - Expect `assertSeeInOrder(['GCash ₱1,000.00 received · '.$ref, $staff->name.' approved '.$ref, $guest->name.' requested '.$cottage->name])`.
  - In `AdminReportsTest::test_owner_dashboard_shows_live_figures`, replace `assertSee('Today at the desk')->assertSee('Pending requests')` with `assertSee('Needs attention')->assertSee('waiting for approval')`.
- [ ] **Step 2: Run** `php artisan test --filter="OwnerOverviewTest|AdminReportsTest"` → FAIL.
- [ ] **Step 3: Implement** the two value classes, both services, the controller change and the view.
- [ ] **Step 4: Run** → PASS, then the full suite → PASS.
- [ ] **Step 5: Checkpoint** — `feat: owner overview built around what needs attention`.

### Task 9: Front desk, queue, booking page and console refinements

**Files:**
- Modify: `resources/css/app.css` (`.ref-code`)
- Modify: `resources/views/staff/dashboard.blade.php` (tiles 20-27, code 51, empty rows 56-58)
- Modify: `resources/views/staff/bookings/index.blade.php` (code 47, empty state 36-40)
- Modify: `resources/views/staff/bookings/show.blade.php` (heading 9, action buttons 38-70)
- Modify: `resources/views/admin/reports/index.blade.php` (tilt 77-95; codes 227, 255), `admin/staff/index.blade.php:48`, `admin/chatbot/index.blade.php:16`
- Test: `tests/Feature/ConsoleLayoutTest.php` (add)

**Interfaces:**
- Produces: `.ref-code` in the components layer — Hanken Grotesk (`font-sans`), weight 600, `font-variant-numeric: tabular-nums`, `letter-spacing: 0.08em`, uppercase. It replaces `font-mono` / `tracking-wider` on booking and receipt codes. Keep `font-mono` on the chatbot context `<pre>` (`admin/chatbot/show.blade.php:53`).
- Motion: front-desk tiles keep `x-tilt` at `"3"`. Remove `x-tilt` and `.tilt` from the reports stat cards, staff cards and chatbot stat card; they keep `x-reveal`.
- Add `tabular-nums` to the money, count and date text in these views.
- Empty states use `<x-empty-state>`:
  - Front desk: `buoy` "No requests waiting.", `anchor` "No arrivals today.", `anchor` "No departures due."
  - Queue: `compass`, keeping its current text.
- Booking page action bar:
  - The "Next step" button row gets `data-action-bar`. Below `lg` it becomes `max-lg:fixed max-lg:inset-x-0 max-lg:bottom-[calc(3.5rem+env(safe-area-inset-bottom))] max-lg:z-30 max-lg:border-t max-lg:border-lagoon-950/10 max-lg:bg-cream/95 max-lg:px-4 max-lg:py-3 max-lg:backdrop-blur max-lg:flex-nowrap max-lg:overflow-x-auto`.
  - The page grid gets `max-lg:pb-24`. Desktop stays unchanged.

- [ ] **Step 1: Write the failing test** `test_the_booking_page_has_a_thumb_reachable_action_bar`: staff, a pending booking, `get(route('staff.bookings.show', $b))->assertSee('data-action-bar', false)->assertSee('Approve')`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** the edits above. Use targeted edits; the user may be editing these views.
- [ ] **Step 4: Run** the full suite → PASS.
- [ ] **Step 5: Look at it** at 390×844 — on a pending booking page the buttons sit above the tab bar and the page content isn't hidden under them.
- [ ] **Step 6: Checkpoint** — `feat: polish front desk, queue and booking page for phones`.

### Task 10: Final verification

- [ ] **Step 1:** `php artisan test` → all pass; the count is the baseline plus the new tests.
- [ ] **Step 2:** `npm run build` → exit 0. This refreshes the stale `public/build` and confirms the `max-lg:` and arbitrary classes compile.
- [ ] **Step 3:** Ask the user to run `php artisan serve` in their own terminal. Claude Code stops background servers when memory runs low, and Vite is already on :5174.
- [ ] **Step 4:** Take screenshots at 375×812, 390×844 and 1366×633 (Claude-in-Chrome, or headless Edge if the screen is locked) of:
  - `/admin` as the owner;
  - `/staff` as staff;
  - the queue;
  - a booking page;
  - `/staff/notifications`;
  - the More sheet open;
  - the bell dropdown open.

  Check each for: no horizontal scroll, the sidebar usable at 633px tall, the tab bar never covering content, and coral used only on warnings and money owed.
- [ ] **Step 5:** Emulate reduced motion: no tilt, no bell ring, and reveals appear instantly.
- [ ] **Step 6:** Check end to end:
  1. A guest books → the staff bell shows it within 30 s → opening it lands on the booking.
  2. A sandbox payment → "GCash payment received".
  3. A guest cancels → an alert appears.
  4. The owner records cash → nothing for the owner; a second owner or partner gets it.
- [ ] **Step 7: Checkpoint** — list the changed files for the user, and commit only if they ask.
