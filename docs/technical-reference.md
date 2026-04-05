# WP Appointments — Technical Reference

## Table of contents

1. [Architecture overview](#1-architecture-overview)
2. [Database schema](#2-database-schema)
3. [REST API](#3-rest-api)
4. [Booking widget (frontend)](#4-booking-widget-frontend)
5. [Booking lifecycle](#5-booking-lifecycle)
6. [Email system](#6-email-system)
7. [Token and reschedule system](#7-token-and-reschedule-system)
8. [Admin panel](#8-admin-panel)
9. [Plugin settings](#9-plugin-settings)
10. [Rate limiting](#10-rate-limiting)
11. [Action hooks reference](#11-action-hooks-reference)
12. [Security notes](#12-security-notes)

---

## 1. Architecture overview

WP Appointments is a self-contained WordPress plugin with no external runtime dependencies. Every component communicates through WordPress action hooks, keeping subsystems decoupled.

```
Browser (booking-widget.js)
        │  REST API (JSON)
        ▼
WPAPPT_Rest_Api            — registers WP REST routes
    ├── WPAPPT_Controller_Booking     — handles POST /bookings
    └── WPAPPT_Controller_Reschedule  — handles GET/POST /reschedule

WordPress action hooks
    ├── WPAPPT_Service_Token   (priority 5)  — generates reschedule tokens
    └── WPAPPT_Service_Email   (priority 10) — sends all transactional emails

WPAPPT_Service_Availability  — slot calculation engine
WPAPPT_Model_*               — thin DB wrappers (no ORM)
WPAPPT_Admin                 — WordPress admin panel
```

**Key design decisions:**

| Decision | Reason |
|---|---|
| Custom DB tables (not CPTs) | Bookings are relational, transactional data — not content |
| WP REST API (not admin-ajax) | Versioned routes, works inside Divi Visual Builder |
| Vanilla JS IIFE widget | No build step, no framework dependency |
| `wp_mail` for email | Inherits any SMTP plugin the site already uses |
| Token stored as SHA-256 hash | Raw token only ever travels in email; database breach cannot reconstruct links |
| Action hook decoupling | Token service and email service can be independently tested and replaced |

---

## 2. Database schema

Five tables are created on plugin activation via `dbDelta()`. `dbDelta` is safe to re-run — it only creates missing tables or adds missing columns; it never drops data.

### `{prefix}appointments_services`

Holds the service catalogue.

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT | Primary key |
| `name` | VARCHAR(120) | Display name shown in the widget |
| `duration_mins` | SMALLINT UNSIGNED | Slot duration used by the availability engine |
| `price` | DECIMAL(8,2) | Informational only — no payment integration |
| `is_active` | TINYINT(1) | `0` hides the service from the widget and API |
| `sort_order` | SMALLINT | Admin-controlled display order |
| `created_at` | DATETIME | Set automatically on insert |

### `{prefix}appointments_availability`

Weekly availability template. Each row defines one open time window for a given day of the week. Multiple rows per day are allowed (e.g. morning and afternoon windows with a lunch gap).

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT | Primary key |
| `day_of_week` | TINYINT UNSIGNED | `0` = Sunday … `6` = Saturday (matches PHP `date('w')`) |
| `start_time` | TIME | Window opens |
| `end_time` | TIME | Window closes — slots must end by this time |
| `is_available` | TINYINT(1) | `0` blocks the entire window without deleting it |
| `label` | VARCHAR(80) | Optional internal label (e.g. "Morning", "Evening") |

### `{prefix}appointments_blocked_slots`

One-off date-specific overrides. Blocks specific hours on specific dates regardless of the weekly template.

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT | Primary key |
| `blocked_date` | DATE | The specific date being blocked |
| `start_time` | TIME | Start of blocked period |
| `end_time` | TIME | End of blocked period |
| `reason` | VARCHAR(120) | Optional internal note |

### `{prefix}appointments_bookings`

All booking records.

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT | Primary key |
| `service_id` | INT UNSIGNED | References services table |
| `status` | VARCHAR(20) | `pending`, `confirmed`, or `cancelled` |
| `appointment_date` | DATE | |
| `start_time` | TIME | |
| `end_time` | TIME | Calculated from start_time + service duration |
| `customer_name` | VARCHAR(120) | |
| `customer_email` | VARCHAR(254) | |
| `customer_phone` | VARCHAR(30) | |
| `injury_notes` | TEXT | Optional health notes from the customer |
| `comments` | TEXT | Optional free-text from the customer |
| `reschedule_token` | VARCHAR(64) | SHA-256 hash of the raw token (64 hex chars) |
| `token_expires_at` | DATETIME | 72 hours after the token was issued |
| `admin_notes` | TEXT | Private notes, only visible in the admin panel |
| `created_at` | DATETIME | Set automatically on insert |
| `updated_at` | DATETIME | Set explicitly on every update |

**Indexes:** `status`, `appointment_date`, `reschedule_token`, `customer_email`.

### `{prefix}appointments_audit_log`

Immutable record of every status transition.

| Column | Type | Notes |
|---|---|---|
| `id` | INT UNSIGNED AUTO_INCREMENT | Primary key |
| `booking_id` | INT UNSIGNED | References bookings table |
| `old_status` | VARCHAR(20) | Status before the change |
| `new_status` | VARCHAR(20) | Status after the change |
| `actor` | VARCHAR(120) | `'admin'` or `'customer'` |
| `created_at` | DATETIME | Set automatically on insert |

---

## 3. REST API

Base namespace: `wpappt/v1`  
Full base URL: `https://yoursite.com/wp-json/wpappt/v1/`

All endpoints accept and return JSON. Endpoints that modify data require a valid WordPress REST nonce passed as the `X-WP-Nonce` header. The widget obtains this nonce via `wp_localize_script` on page load.

---

### `GET /services`

Returns all active services. Used by the widget on initialisation to populate the service selection step.

**No parameters.**

**Response `200`:**
```json
[
  {
    "id": 1,
    "name": "Swedish Massage",
    "duration_mins": 60,
    "price": 75.00
  }
]
```

---

### `GET /availability`

Returns available time slots for a given service and date. The availability engine:

1. Loads open time windows from the weekly template for that day of week.
2. Loads all blocked slots and existing bookings for that date.
3. Generates consecutive candidate slots of exactly `duration_mins` length within each window.
4. Discards any candidate that overlaps a blocked slot or an existing booking.
5. Returns the remainder.

**Parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `service_id` | integer | Yes | ID of the selected service |
| `date` | string | Yes | `YYYY-MM-DD` format, must not be in the past |

**Response `200`:**
```json
[
  { "start_time": "09:00", "end_time": "10:00" },
  { "start_time": "10:00", "end_time": "11:00" },
  { "start_time": "14:00", "end_time": "15:00" }
]
```

An empty array means nothing is available for that date (not an error).

---

### `POST /bookings`

Creates a new booking. Requires `X-WP-Nonce` header.

Before writing to the database, the controller:

1. Checks the rate limit (5 submissions per IP per hour).
2. Sanitises all input fields.
3. Validates required fields and formats server-side (duplicate of widget validation — never trust client-only checks).
4. Re-runs the availability check to prevent double-booking between when the user loaded slots and when they submitted.
5. Calculates `end_time` from `start_time + service.duration_mins`.
6. Creates the booking with status `pending`.
7. Fires `do_action('wpappt_booking_created', $booking_id)`.

**Request body:**

| Field | Type | Required | Description |
|---|---|---|---|
| `service_id` | integer | Yes | |
| `appointment_date` | string | Yes | `YYYY-MM-DD` |
| `start_time` | string | Yes | `HH:MM` |
| `customer_name` | string | Yes | |
| `customer_email` | string | Yes | Must be a valid email address |
| `customer_phone` | string | Yes | |
| `injury_notes` | string | No | |
| `comments` | string | No | |

**Response `201`:**
```json
{
  "booking_id": 42,
  "message": "Your booking request has been received. We will confirm it shortly."
}
```

**Error responses:**

| Code | Error key | Cause |
|---|---|---|
| 409 | `slot_unavailable` | Slot was taken between load and submit |
| 422 | `missing_field` / `invalid_email` / `invalid_date` / `invalid_time` | Validation failure |
| 429 | `rate_limited` | Rate limit exceeded |
| 500 | `booking_failed` | Database write failure |

---

### `GET /reschedule`

Validates a reschedule token and returns booking details so a reschedule form can be pre-populated.

**Parameters:**

| Parameter | Type | Required |
|---|---|---|
| `token` | string | Yes — the raw token from the email link |

**Response `200`:**
```json
{
  "id": 42,
  "service_name": "Swedish Massage",
  "duration_mins": 60,
  "appointment_date": "2026-04-10",
  "start_time": "10:00",
  "end_time": "11:00",
  "customer_name": "Jane Smith",
  "status": "confirmed"
}
```

Note: sensitive fields (token hash, admin notes, email, phone) are deliberately excluded from this response.

**Response `404`:** Token is invalid, expired, or already used.

---

### `POST /reschedule`

Applies a reschedule. Requires `X-WP-Nonce` header.

The controller:

1. Rate-limits (10 requests per IP per hour).
2. Validates the token.
3. Validates the new date and time.
4. Checks for overlapping bookings (excluding the booking's own current slot).
5. Updates `appointment_date`, `start_time`, `end_time`.
6. Resets status to `pending` so the admin reviews the change.
7. Invalidates the used token and issues a fresh one (token rotation).
8. Fires `do_action('wpappt_booking_rescheduled', $booking_id, $new_reschedule_link)`.

**Request body:**

| Field | Type | Required |
|---|---|---|
| `token` | string | Yes |
| `appointment_date` | string | Yes — `YYYY-MM-DD` |
| `start_time` | string | Yes — `HH:MM` |

**Response `200`:**
```json
{ "message": "Your appointment has been rescheduled successfully." }
```

---

## 4. Booking widget (frontend)

The widget is a vanilla JavaScript IIFE loaded from `assets/js/booking-widget.js`. It attaches to a container element with `id="wpappt-booking-widget"` and manages its own internal state through six sequential steps.

### Initialisation

On `DOMContentLoaded`, the widget:

1. Finds `#wpappt-booking-widget` on the page. If absent, exits silently.
2. Reads `window.WPAppt` (injected by `wp_localize_script`) for `apiUrl` and `nonce`.
3. Renders the progress bar and step panels.
4. Fetches the service list from `GET /services`.

`window.WPAppt` shape:
```js
{
  apiUrl: "https://yoursite.com/wp-json/wpappt/v1/",
  nonce:  "abc123def456"   // WordPress REST nonce, rotates per session
}
```

### Step flow

```
Step 1 — Service selection
  ↓  (click service card)
Step 2 — Date picker (inline calendar)
  ↓  (click Next → fetches GET /availability)
Step 3 — Time slot selection
  ↓  (click slot)
Step 4 — Customer details form
  ↓  (submit form → client-side validation)
Step 5 — Booking summary / review
  ↓  (click Confirm → POST /bookings)
Step 6 — Success screen
```

The user can navigate backwards freely (Back button) until they reach the success screen. Form field values are preserved in state when navigating back from step 5 to step 4.

### Calendar

The date picker is a custom inline month calendar rendered in HTML/JS — no external library is used. Key behaviours:

- Past dates are rendered as disabled and non-interactive.
- Prev-month navigation is disabled when already on the current month.
- Clicking a date stores it in state and enables the Next button. Availability is not fetched until Next is clicked (avoids one API call per date click).
- The currently selected date is highlighted when navigating between months.

### Client-side validation (step 4)

| Field | Rule |
|---|---|
| Name | Non-empty after trim |
| Email | Non-empty + basic regex (`/^[^\s@]+@[^\s@]+\.[^\s@]+$/`) |
| Phone | Non-empty after trim |

Errors are shown inline beneath each field with `role="alert"`. The form does not submit until all required fields pass.

### HTML output safety

All user-supplied values rendered into HTML go through an `esc()` helper that encodes `&`, `<`, `>`, `"`, and `'`. This prevents XSS from values stored in state.

---

## 5. Booking lifecycle

```
             Customer submits form
                      │
                      ▼
                  [ pending ]
                      │
            ┌─────────┴─────────┐
            │                   │
      Admin confirms       Admin cancels
            │                   │
            ▼                   ▼
       [ confirmed ]       [ cancelled ]
            │
     Customer reschedules
      (via token link)
            │
            ▼
        [ pending ]   ← admin reviews again
```

**Status rules:**
- Only `pending → confirmed`, `pending → cancelled`, and `confirmed → cancelled` are valid admin transitions.
- Customer reschedule always resets status to `pending`, regardless of previous status.
- Every transition is recorded in the audit log with the actor (`admin` or `customer`).
- Token is nulled immediately when a booking is cancelled, invalidating any outstanding reschedule links.

---

## 6. Email system

`WPAPPT_Service_Email` listens on WordPress action hooks and sends all transactional email. It uses `wp_mail()`, which means any SMTP plugin installed on the site (e.g. WP Mail SMTP, Postmark) is automatically used.

All emails are HTML, rendered from PHP templates in `templates/emails/`. Each specific template is wrapped in `templates/emails/base.php` which provides the shared header and footer shell.

### Emails sent

| Trigger | Recipients | Template |
|---|---|---|
| New booking submitted | Customer + Admin | `booking-received-customer.php` + `booking-received-admin.php` |
| Admin confirms a booking | Customer | `booking-confirmed.php` |
| Admin cancels a booking | Customer | `booking-cancelled.php` |
| Customer reschedules | Customer + Admin | `reschedule-customer.php` + `reschedule-admin.php` |
| Admin sends follow-up | Customer | `followup.php` |

### Hook wiring and priority

The email service registers at **priority 10** on `wpappt_booking_status_changed`. The token service registers at **priority 5** on the same hook. This ordering is intentional:

1. Token service fires first (priority 5) → generates a reschedule token → fires `wpappt_booking_confirmed` with the fresh link.
2. Email service fires second (priority 10) → listens on `wpappt_booking_confirmed` → sends the confirmation email with the link already embedded.

This means the confirmation email always contains a valid, freshly-generated reschedule link without the two services needing to know about each other.

### Email headers

All emails are sent with:
```
Content-Type: text/html; charset=UTF-8
From: {Sender Name} <{Admin Email}>
```

The sender name is sanitised with a custom `email_header_value()` helper that strips newlines to prevent header injection.

### Template data

Every template receives the following variables:

| Variable | Contents |
|---|---|
| `$booking` | Full booking row from the database |
| `$service` | Service row from the database |
| `$site_name` | `get_bloginfo('name')` |
| `$admin_email` | Value of `wpappt_admin_email` option |
| `$admin_panel_url` | Direct link to the booking detail page in wp-admin |
| `$booking_page_url` | Frontend booking page URL (from `wpappt_booking_page` option) |
| `$reschedule_link` | Raw reschedule URL (only set for confirmation and reschedule emails) |

---

## 7. Token and reschedule system

### Token generation

When a booking is confirmed, the token service:

1. Generates 32 cryptographically random bytes via `random_bytes(32)`.
2. Encodes them as a 64-character hex string — this is the **raw token** that travels in the email URL.
3. Hashes the raw token with SHA-256 — this is what is stored in the `reschedule_token` column.
4. Sets `token_expires_at` to 72 hours from now.

The raw token is never stored. If the database is compromised, an attacker cannot reconstruct reschedule links from the stored hashes.

### Token validation

When a customer clicks a reschedule link:

1. The raw token is extracted from the query string.
2. It is hashed with SHA-256.
3. The database is queried for a booking where `reschedule_token = {hash}` AND `token_expires_at > NOW()` AND `reschedule_token IS NOT NULL`.
4. `hash_equals()` is used for the final comparison to prevent timing-side-channel attacks.

A token fails validation if:
- It does not match any hash in the database.
- It has expired (> 72 hours old).
- It has been nulled (booking was cancelled).
- It has been rotated (customer already rescheduled using this link).

### Token rotation (replay prevention)

After a successful reschedule, `consume()` immediately replaces the used token with a new one. Any previously copied or cached reschedule link is invalidated. The new raw token is passed into the `wpappt_booking_rescheduled` action so the confirmation email contains the next valid link.

### Reschedule URL format

```
https://yoursite.com/book-a-session/?reschedule={64-char-hex-token}
```

The base URL is the booking page configured in plugin settings. If no page is configured, the site home URL is used as a fallback.

---

## 8. Admin panel

The admin panel is registered under **Appointments** in the WordPress sidebar with four sub-pages.

### Bookings list

URL: `wp-admin/admin.php?page=wpappt-bookings`

Displays all bookings in a `WP_List_Table` with:
- Status filter tabs (All / Pending / Confirmed / Cancelled) with counts.
- Columns: ID, customer name, service, date, time, status, date received.
- Row actions: View, Confirm, Cancel.
- Sortable columns: ID, customer name, date, status.

### Booking detail

URL: `wp-admin/admin.php?page=wpappt-bookings&action=view&id={id}`

Shows the full booking record with:
- Customer information (name, email, phone, injury notes, comments).
- Appointment details (service, date, time).
- Status badge with current state.
- Confirm and Cancel action buttons.
- Admin notes field (private, never shown to customer).
- Audit log showing all status transitions.
- Follow-up email form — allows sending a custom message to the customer.

Status changes and follow-up emails are submitted via WordPress admin-ajax (`WPAPPT_Controller_Admin_Ajax`), not the REST API, since they are admin-only actions that rely on WordPress nonces and `current_user_can('manage_options')` capability checks.

### Services page

URL: `wp-admin/admin.php?page=wpappt-services`

Allows adding, editing, and removing services. Each service has a name, duration (minutes), price, and active/inactive toggle.

### Availability page

URL: `wp-admin/admin.php?page=wpappt-availability`

Two sections:

**Weekly template** — a table with one row per day of the week. Each row has:
- An enabled/disabled toggle.
- Start time and end time inputs.
- Optional label.

Changes to the weekly template take effect immediately for all future availability lookups.

**Blocked slots** — a list of specific date/time ranges that override the weekly template. Used to block out holidays, personal appointments, or any other one-off unavailability.

---

## 9. Plugin settings

URL: `wp-admin/admin.php?page=wpappt-settings`

Settings are stored as individual WordPress options (not serialised) using the WordPress Settings API.

| Option key | Default | Description |
|---|---|---|
| `wpappt_admin_email` | WordPress admin email | All booking notification emails are delivered here |
| `wpappt_sender_name` | Site name | Appears in the `From:` header of every email sent by the plugin |
| `wpappt_booking_page` | `0` (none) | The page ID where the booking widget is embedded — used to build reschedule links in confirmation emails |

> **Important:** `wpappt_booking_page` must be set before any bookings are confirmed. If it is not set, reschedule links will point to the site home page instead of the booking widget.

---

## 10. Rate limiting

Public REST endpoints are protected by a transient-based fixed-window rate limiter (`WPAPPT_Helper_Rate_Limiter`).

| Endpoint | Limit | Window |
|---|---|---|
| `POST /bookings` | 5 requests | 1 hour per IP |
| `GET /reschedule` | 10 requests | 1 hour per IP |
| `POST /reschedule` | 10 requests | 1 hour per IP |

The rate limiter tracks requests by IP address using a WordPress transient keyed by `wpappt_rl_{action}_{md5(ip)[0:12]}`. The counter window is fixed — it starts on the first request and resets after the window expires, regardless of subsequent activity.

IP detection checks `X-Forwarded-For` first (for sites behind a proxy or CDN) and falls back to `REMOTE_ADDR`. The limiter is a deterrent against bots and accidental spam; it is not a security boundary.

---

## 11. Action hooks reference

These hooks are the internal event bus of the plugin. You can use them in custom code (e.g. in your theme's `functions.php`) to extend behaviour without modifying plugin files.

| Hook | Fired by | Arguments | Use case |
|---|---|---|---|
| `wpappt_booking_created` | Booking controller, after DB insert | `int $booking_id` | Send additional notifications, sync to calendar |
| `wpappt_booking_status_changed` | Booking model, on every status change | `int $booking_id`, `string $new_status` | Audit logging, custom notifications |
| `wpappt_booking_confirmed` | Token service (priority 5) | `int $booking_id`, `string $reschedule_link` | Override or extend the confirmation email |
| `wpappt_booking_rescheduled` | Reschedule controller, after successful reschedule | `int $booking_id`, `string $new_reschedule_link` | Notify a third-party, log changes |
| `wpappt_send_followup_email` | Admin-ajax controller | `int $booking_id`, `string $message` | Hook into manual follow-ups |

**Example — send an SMS on booking creation:**
```php
add_action( 'wpappt_booking_created', function ( int $booking_id ): void {
    // your SMS API call here
} );
```

---

## 12. Security notes

| Area | Mechanism |
|---|---|
| SQL injection | All queries use `$wpdb->prepare()`. Column names in ORDER BY are whitelisted against a hardcoded list (`SORTABLE_COLUMNS`). |
| XSS (admin) | All output in admin views goes through `esc_html()`, `esc_attr()`, or `esc_url()`. |
| XSS (widget) | All values rendered into HTML by the JS widget pass through a local `esc()` function that encodes five HTML special characters. |
| CSRF (admin actions) | Admin-ajax handlers verify a WordPress nonce and `current_user_can('manage_options')` before processing. |
| CSRF (REST API) | Public read endpoints require no authentication. Write endpoints (`POST /bookings`, `POST /reschedule`) require the `X-WP-Nonce` header, validated automatically by WordPress REST middleware. |
| Email header injection | The sender name option is passed through `WPAPPT_Helper_Sanitizer::email_header_value()`, which strips all newline characters before use in the `From:` header. |
| Token security | Raw tokens are never stored. The database holds only SHA-256 hashes. Token comparison uses `hash_equals()` to prevent timing attacks. Tokens expire after 72 hours and are single-use (rotated on every reschedule, nulled on cancellation). |
| Rate limiting | Public booking and reschedule endpoints are limited to 5–10 requests per IP per hour. |
| Input sanitisation | All REST input is sanitised via WordPress callbacks (`sanitize_text_field`, `sanitize_email`, `wp_kses_post`, `absint`) declared in the route `args` definition, before reaching any controller. |
