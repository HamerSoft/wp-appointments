# Technical Architecture: WP Appointments Plugin

## Plugin File / Folder Structure

```
wp-appointments/
├── wp-appointments.php              # Main plugin file — bootstrap, constants, hooks
├── uninstall.php                    # Drops tables on uninstall
│
├── includes/
│   ├── class-plugin.php             # Singleton orchestrator, registers all subsystems
│   ├── class-activator.php          # DB table creation on activation
│   ├── class-deactivator.php        # Cleanup on deactivation
│   │
│   ├── models/
│   │   ├── class-booking.php        # CRUD wrapper for wp_appointments_bookings
│   │   ├── class-service.php        # CRUD wrapper for wp_appointments_services
│   │   └── class-availability.php   # CRUD wrapper for wp_appointments_availability
│   │
│   ├── controllers/
│   │   ├── class-booking-controller.php      # REST endpoint logic
│   │   ├── class-reschedule-controller.php   # Token-based reschedule endpoint
│   │   └── class-admin-ajax-controller.php   # Admin confirm/cancel/follow-up actions
│   │
│   ├── services/
│   │   ├── class-email-service.php           # Sends all transactional emails via wp_mail
│   │   ├── class-availability-service.php    # Slot calculation logic
│   │   └── class-token-service.php           # Generate/validate reschedule tokens
│   │
│   └── helpers/
│       └── class-sanitizer.php               # Centralised input sanitization
│
├── admin/
│   ├── class-admin.php                       # Registers admin menus, enqueues assets
│   ├── class-bookings-list-table.php         # Extends WP_List_Table
│   ├── class-settings-page.php              # WP Settings API wrappers
│   └── views/
│       ├── bookings-list.php
│       ├── booking-detail.php
│       ├── services-page.php
│       └── availability-page.php
│
├── frontend/
│   ├── class-divi-module.php                 # Registers the custom Divi module
│   └── class-rest-api.php                    # Registers REST routes
│
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── booking-widget.css
│   └── js/
│       ├── admin.js
│       └── booking-widget.js                 # Vanilla JS widget controller
│
└── templates/
    └── emails/
        ├── base.php                          # Shared HTML email shell
        ├── booking-received-customer.php
        ├── booking-received-admin.php
        ├── booking-confirmed.php
        ├── booking-cancelled.php
        ├── reschedule-customer.php
        └── reschedule-admin.php
```

---

## Database Schema

### Decision: Custom Tables (not CPTs)

Custom Post Types are designed for content with archive pages, taxonomies, and editorial workflows. Bookings are structured transactional records with relational data. Custom tables give clean SQL, proper indexes, referential integrity, and straightforward `$wpdb` queries.

### `{prefix}appointments_services`

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| name | VARCHAR(120) | |
| duration_mins | SMALLINT | |
| price | DECIMAL(8,2) | |
| is_active | TINYINT(1) DEFAULT 1 | |
| sort_order | SMALLINT DEFAULT 0 | |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

### `{prefix}appointments_availability`

Weekly template — one row per day-of-week + time block.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| day_of_week | TINYINT | 0=Sun … 6=Sat |
| start_time | TIME | |
| end_time | TIME | |
| is_available | TINYINT(1) DEFAULT 1 | |
| label | VARCHAR(80) NULL | e.g. "School pickup" |

### `{prefix}appointments_blocked_slots`

One-off date overrides.

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| blocked_date | DATE | Index on this column |
| start_time | TIME | |
| end_time | TIME | |
| reason | VARCHAR(120) NULL | |

### `{prefix}appointments_bookings`

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| service_id | INT UNSIGNED FK | → services.id |
| status | ENUM('pending','confirmed','cancelled') DEFAULT 'pending' | |
| appointment_date | DATE | |
| start_time | TIME | |
| end_time | TIME | |
| customer_name | VARCHAR(120) | |
| customer_email | VARCHAR(254) | |
| customer_phone | VARCHAR(30) | |
| injury_notes | TEXT NULL | |
| comments | TEXT NULL | |
| reschedule_token | VARCHAR(64) NULL | SHA-256 hex, one-time use |
| token_expires_at | DATETIME NULL | 72h TTL |
| admin_notes | TEXT NULL | Internal follow-up field |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |
| updated_at | DATETIME ON UPDATE CURRENT_TIMESTAMP | |

Indexes: `(status)`, `(appointment_date, start_time)`, `(reschedule_token)`, `(customer_email)`

---

## Admin Panel

- Top-level "Appointments" menu with four sub-pages: **Bookings**, **Services**, **Availability**, **Settings**
- **Bookings list** extends `WP_List_Table` — sortable columns, bulk Confirm/Cancel, status filter tabs (All | Pending | Confirmed | Cancelled)
- **Confirm/Cancel** use standard `admin-post.php` POST actions with nonces (page refresh — no AJAX needed)
- **Booking detail** view includes all fields, admin notes textarea, and "Send Follow-up Email" button
- **Services/Availability** pages use plain HTML forms posting to `admin-post.php` with `$wpdb` queries
- **Settings** page uses the WP Settings API for flat options (admin email, sender name, booking page ID)

---

## Frontend Booking Widget

### Divi Module

- Extends `ET_Builder_Module`, registered on the `et_builder_ready` hook
- Exposes module settings in the Visual Builder (accent color, button label, etc.)
- `render()` outputs a single `<div id="wpappt-booking-widget" data-nonce="..." data-rest-url="...">` container
- A `[wpappt_booking]` shortcode is also registered as a fallback for non-Divi use

### JS Widget (Vanilla JS)

Multi-step form rendered entirely in JS against the REST API — no React, no Vue. Written as a self-contained IIFE.

**Steps:**
1. Service selection
2. Date picker
3. Time slot picker
4. Customer details (name, email, phone, injury notes, comments)
5. Confirmation summary
6. Success screen

---

## Booking Form Submission — REST API

### Decision: WP REST API (not admin-ajax, not form POST)

- `admin-ajax.php` is a legacy pattern requiring manual auth handling
- Standard form POST breaks multi-step UX and the Divi Visual Builder preview
- REST API provides named versioned routes, built-in parameter validation, and consistent JSON responses

### Routes

```
GET  /wpappt/v1/services                           → list active services
GET  /wpappt/v1/availability?service_id=&date=     → available time slots
POST /wpappt/v1/bookings                           → create booking (status: pending)
GET  /wpappt/v1/reschedule?token=                  → validate token, return booking details
POST /wpappt/v1/reschedule                         → submit new date/time
```

- `GET` endpoints are public (`permission_callback: __return_true`)
- `POST /bookings` is public but requires a nonce injected via `wp_localize_script` and sent as `X-WP-Nonce`
- `POST /reschedule` is protected by the one-time token only

---

## Self-Reschedule Mechanism

1. Every confirmation and cancellation email includes a reschedule link: `https://site.com/booking-page/?wpappt_reschedule=<token>`
2. Token is generated with `random_bytes(32)` (64-char hex), stored on the booking row, expires after 72h
3. Widget detects the `?wpappt_reschedule` URL param and calls `GET /wpappt/v1/reschedule?token=` to pre-fill the form
4. Customer picks a new date/time (service is locked)
5. On `POST /wpappt/v1/reschedule`:
   - Token validated and nulled (one-time use)
   - Booking updated to new date/time, status reset to `pending`
   - Fresh token generated for next reschedule link
   - Emails sent to admin and customer

---

## Email System

### Transport: `wp_mail`

Respects whatever SMTP plugin is configured on the site (Postmark, SendGrid, etc.) — no plugin-level mail config needed.

### Email Service Methods

- `send_booking_received_customer(int $booking_id)`
- `send_booking_received_admin(int $booking_id)`
- `send_booking_confirmed(int $booking_id)`
- `send_booking_cancelled(int $booking_id)`
- `send_reschedule_customer(int $booking_id)`
- `send_reschedule_admin(int $booking_id)`
- `send_followup(int $booking_id, string $message)`

### Templating

- PHP templates in `templates/emails/`, loaded via `ob_start()` + `extract($data)` + `include`
- `base.php` provides a shared HTML shell (logo, brand color from settings, footer)
- `Content-Type: text/html` set via scoped `wp_mail_content_type` filter

---

## Security

### File-Level Guards
Every PHP file in the plugin must begin with:
```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```
`uninstall.php` uses the stricter form:
```php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
```
Without these guards, files are directly accessible via URL and can trigger autoloader side effects or (in the case of `uninstall.php`) drop tables.

### Nonces
- Admin POST actions: `wp_nonce_field` / `wp_verify_nonce` with action-specific names (e.g. `wpappt_confirm_booking_{$id}`). Failed verification calls `wp_die()`.
- Frontend REST POSTs: nonce injected via `wp_localize_script`, sent as `X-WP-Nonce` header
- Reschedule: one-time token is the authenticator — no separate nonce needed
- CORS: do not add `Access-Control-Allow-Origin: *` to any REST response. WordPress core defaults apply.

### Capability Checks
- All admin pages and `admin-post.php` handlers: `current_user_can('manage_options')`
- Public REST endpoints: `permission_callback: '__return_true'` (explicit)

### Input Sanitization
- Text fields: `sanitize_text_field`
- Email: `sanitize_email` + `is_email` validation
- Notes/comments: `wp_kses_post`
- Integer IDs: `(int)` cast
- All DB queries: `$wpdb->prepare()` — no string interpolation
- LIKE clauses: `$wpdb->esc_like()` applied before interpolation to prevent `%` / `_` wildcard injection
- Customer name used in email headers (Reply-To, etc.): strip `\r` and `\n` before use — `str_replace(["\r", "\n"], '', $name)` — to prevent email header injection
- Follow-up email message: `sanitize_textarea_field` (plain text) or `wp_kses_post` (HTML) — must be decided explicitly; default to `sanitize_textarea_field`
- Availability endpoint: `date` parameter validated as a real calendar date via `DateTime::createFromFormat('Y-m-d', $date)`; `service_id` must match an active service row

### Output Escaping
- Admin views: `esc_html` for text nodes, `esc_attr` for attribute values, `esc_url` for URLs, `esc_textarea` for textarea element values (not `esc_html` — they handle `"` differently)
- Email templates: `esc_html` for all customer-supplied content
- JS: `textContent` assignment only (never `innerHTML`) for customer data
- `wp_localize_script`: pass only the nonce and REST URL — no raw booking data or user input

### Token Security (`class-token-service.php`)
- Generated with `random_bytes(32)` (64-char hex) — cryptographically secure
- **Stored as `SHA-256` hash** in the DB column; the raw token is returned only in the email link. If the DB is compromised, stored hashes cannot be replayed.
- On validation: hash the incoming token, then query `WHERE reschedule_token = SHA256(%s) AND reschedule_token IS NOT NULL AND token_expires_at > NOW()`. The `IS NOT NULL` guard prevents an empty-string match against nulled tokens.
- **Comparison uses `hash_equals()`** (not `===`) to prevent timing-based token oracle attacks.
- One-time use: token and expiry are nulled immediately on successful reschedule.
- On booking cancellation: `reschedule_token` and `token_expires_at` are nulled immediately — a cancelled booking cannot be rescheduled via an outstanding token.
- After a successful reschedule, a fresh token is generated for the next reschedule link.
- TTL: 72 hours.

### Rate Limiting
- `POST /wpappt/v1/bookings`: transient-based counter keyed on IP — max 5 attempts per hour
- `GET /wpappt/v1/reschedule?token=`: same transient-based IP rate limit — max 10 attempts per hour. Token entropy makes brute force infeasible, but rate limiting removes unnecessary exposure.

### Data Storage
- Plugin settings stored as JSON (`json_encode` / `json_decode`) rather than PHP-serialized values. Avoids PHP object injection risk if stored data is ever passed through `unserialize`.

### Audit Logging
Every booking status transition is written to `{prefix}appointments_audit_log`:

| Column | Type | Notes |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| booking_id | INT UNSIGNED | |
| old_status | VARCHAR(20) | |
| new_status | VARCHAR(20) | |
| actor | VARCHAR(120) | `'admin'`, `'customer'`, or WP username |
| created_at | DATETIME DEFAULT CURRENT_TIMESTAMP | |

Failed nonce verifications and rate-limit hits are written to the PHP error log via `error_log()`.

### Summary of Controls by File

| Control | File |
|---|---|
| `ABSPATH` guard | All PHP files |
| `WP_UNINSTALL_PLUGIN` guard | `uninstall.php` |
| Token hashing (SHA-256 stored, raw in email) | `class-token-service.php` |
| `hash_equals()` for token comparison | `class-token-service.php` |
| Token IS NOT NULL + expiry in query | `class-token-service.php` |
| Null token on cancellation | `class-booking-controller.php` |
| `$wpdb->esc_like()` for search | `class-bookings-list-table.php` |
| Email header newline stripping | `class-email-service.php` |
| `esc_textarea()` for notes fields | All admin views |
| Date + service_id validation | `class-availability-service.php` |
| Rate limit on `GET /reschedule` | `class-reschedule-controller.php` |
| JSON for plugin options | `class-settings-page.php` |
| Audit log on status transitions | `class-booking-controller.php`, `class-reschedule-controller.php` |

---

## Recommended Build Order

1. Plugin bootstrap, autoloader, activator (DB tables)
2. Model classes (Booking, Service, Availability)
3. Admin panel — Settings, Services, Availability
4. Availability service (slot calculation logic)
5. REST API routes + Booking controller
6. Email service + templates
7. Token service + reschedule endpoints
8. Frontend JS widget
9. Divi module + shortcode fallback
