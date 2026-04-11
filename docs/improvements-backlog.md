# Improvements Backlog

Planned improvements beyond the initial build. Each item includes implementation notes to make picking them up straightforward.

---

## ~~1. Reminder emails~~ ✓ Done

**What:** Send an automated email to the customer the day before their confirmed appointment.

**Why:** Reduces no-shows without any manual effort.

**Implementation notes:**

- Register a WP-Cron event on plugin activation: `wp_schedule_event( time(), 'daily', 'wpappt_send_reminders' )`.
- Hook `wpappt_send_reminders` to a method in `WPAPPT_Service_Email` (or a new scheduler class).
- The handler queries all bookings where `status = 'confirmed'` and `appointment_date = tomorrow`.
- Add a new email template: `templates/emails/reminder-customer.php`.
- Add a `reminder_sent_at` column to `{prefix}appointments_bookings` (nullable datetime) to prevent duplicate sends if the cron runs more than once in a day.
- De-register the cron event in `class-deactivator.php` via `wp_clear_scheduled_hook`.
- Add a setting under **Appointments → Settings** to enable/disable reminders, and optionally configure how many days in advance (default: 1).

---

## ~~3. Admin booking page URL validation~~ ✓ Done

**What:** Validate that the booking page selected in settings actually exists and has the booking widget on it. Warn the admin if it is missing or unpublished.

**Why:** Reschedule links in confirmation emails point to this page. A wrong or deleted page produces a broken link that customers can't use to reschedule — a silent failure that is hard to diagnose.

**Implementation notes:**

- In `WPAPPT_Admin_Settings_Page`, after saving options, check `get_post( $page_id )` — verify the post exists, is of type `page`, and has `post_status = 'publish'`.
- If the check fails, add an admin notice via `add_settings_error()` with a clear message: "The selected booking page is not published. Reschedule links in emails will be broken."
- Also validate on the settings page render (not just on save) so the warning is visible when the admin visits the page.
- Optionally check that the page content contains `[wpappt_booking]` or the Divi module, though that is harder to detect reliably and a soft warning is sufficient.

---

## ~~4. Double-booking protection~~ ✓ Done

**What:** Re-check slot availability inside a DB transaction at the moment the booking row is inserted, so two simultaneous submissions cannot claim the same slot.

**Why:** The current flow checks availability before inserting, but there is a race window between the check and the insert. Low probability for a solo practitioner but worth closing.

**Implementation notes:**

- In `WPAPPT_Controller_Booking::create()` (or the model), wrap the availability check + insert in a transaction:
  ```php
  $wpdb->query( 'START TRANSACTION' );
  // re-run availability check with FOR UPDATE or re-query bookings
  // if slot is still free: INSERT booking
  // if taken: ROLLBACK, return 409 Conflict
  $wpdb->query( 'COMMIT' );
  ```
- MySQL's `InnoDB` supports row-level locking. Use `SELECT ... FOR UPDATE` on the bookings table for the overlapping time range to lock those rows during the transaction.
- Return HTTP 409 with a user-friendly message: "This time slot was just taken. Please go back and choose another time."
- The JS widget should handle a 409 response gracefully — show the error on step 5 and re-enable the submit button (already partially handled by the generic error path).

---

## ~~5. Token expiry cleanup (WP-Cron)~~ ✓ Done

**What:** A weekly cron job that nulls out expired reschedule tokens from the bookings table.

**Why:** Expired tokens are harmless but accumulate over time. Cleaning them prevents the token validator from ever accidentally accepting a token that was stored long-term.

**Implementation notes:**

- Register on activation: `wp_schedule_event( time(), 'weekly', 'wpappt_cleanup_tokens' )`.
- The handler runs:
  ```sql
  UPDATE {prefix}appointments_bookings
  SET reschedule_token = NULL, token_expires_at = NULL
  WHERE token_expires_at < NOW()
  ```
- De-register in `class-deactivator.php`.
- Can be combined with the reminder email cron (both run daily/weekly — consider a single `wpappt_cron` event that dispatches both tasks).

---

## 6. Rate limiting on the booking endpoint

**What:** Limit how many booking submissions can be made from a single IP address within a time window.

**Why:** Without rate limiting the `POST /bookings` endpoint can be abused to flood the admin inbox with fake bookings, or to enumerate available slots.

**Implementation notes:**

- Implement in `WPAPPT_Controller_Booking` before any business logic runs.
- Use the WordPress transients API as a lightweight counter:
  ```php
  $key     = 'wpappt_rl_' . md5( $_SERVER['REMOTE_ADDR'] );
  $count   = (int) get_transient( $key );
  $limit   = 5; // submissions per window
  $window  = 10 * MINUTE_IN_SECONDS;

  if ( $count >= $limit ) {
      return new WP_Error( 'too_many_requests', __( 'Too many requests. Please try again later.', 'wp-appointments' ), [ 'status' => 429 ] );
  }
  set_transient( $key, $count + 1, $window );
  ```
- Consider also rate-limiting by email address (not just IP) to handle users behind NAT.
- Return HTTP 429. The JS widget should display this as a user-visible error on step 5 rather than a generic failure.
- Make the limit and window configurable via constants (`WPAPPT_RATE_LIMIT`, `WPAPPT_RATE_WINDOW`) so they can be overridden in `wp-config.php` without touching plugin code.

---

## 7. Booking detail / edit view in the admin

**What:** A single-booking detail page in the admin where the admin can view all fields, change the status, and send a follow-up email — accessible from the bookings list table.

**Why:** The list table shows status and date, but the admin needs to see injury notes, comments, and customer contact details without leaving WordPress. Confirms and cancels should be one click from this screen.

**Implementation notes:**

- Add a `view` action link to each row in `WPAPPT_Admin_Bookings_List_Table` pointing to `admin.php?page=wpappt-bookings&action=view&id={id}`.
- In `WPAPPT_Admin`, detect `action=view` and render a detail template (`admin/views/booking-detail.php`).
- The detail view should show: service, date, time, customer name/email/phone, injury notes, comments, current status, and created/updated timestamps.
- Action buttons:
  - **Confirm** — POST to an admin-ajax handler, sets status to `confirmed`, fires `wpappt_booking_confirmed`.
  - **Cancel** — POST to handler, sets status to `cancelled`, fires `wpappt_booking_status_changed`.
  - **Send follow-up** — textarea + submit, fires `wpappt_send_followup_email` (already implemented in the email service).
- All state changes go through the existing `WPAPPT_Controller_Admin_Ajax` — wire new actions there.
- Add a "Back to bookings" link at the top of the detail view.
