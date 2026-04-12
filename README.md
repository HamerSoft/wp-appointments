# WP Appointments

A custom WordPress booking plugin for a solo massage therapist. Replaces the youcanbookme third-party service with a self-hosted, fully owned booking flow integrated directly into a Divi site.

- No external services or API keys required
- No online payments — customers pay in person
- No multi-user or team features — built for a single practitioner
- Works with any SMTP plugin already installed on the site

---

## Features

### For customers
- Multi-step booking form embedded directly on any page (Divi module or shortcode)
- Service selection with name, duration, and price
- Inline month calendar date picker
- Real-time time slot availability (slots that are already booked or blocked do not appear)
- Customer details form with client-side and server-side validation
- Booking summary review before submitting
- Self-service rescheduling via a secure token link sent in the confirmation email

### For the admin
- Bookings list in wp-admin with status filter tabs (All / Pending / Confirmed / Cancelled)
- Per-booking detail view: confirm, cancel, add private notes, send a custom follow-up email
- Service catalogue management: add, edit, deactivate services
- Weekly availability template: set open hours per day, with optional multiple windows per day
- One-off blocked slots: block specific date/time ranges without touching the weekly template
- Full audit log on every booking showing every status transition with actor and timestamp

### Transactional emails
Every significant event sends an email automatically:

| Event | Recipients |
|---|---|
| New booking submitted | Customer + Admin |
| Booking confirmed | Customer (with reschedule link) |
| Booking cancelled | Customer |
| Customer reschedules | Customer (with new reschedule link) + Admin |
| Appointment reminder | Customer (N days before, configurable) |
| Admin sends follow-up | Customer |

---

## Requirements

- WordPress 6.0 or later
- PHP 8.0 or later
- MySQL 5.7+ / MariaDB 10.3+
- Divi theme (optional — only needed for the Visual Builder module)

---

## Installation

### 1. Copy the plugin folder

Copy the `wp-appointments/` directory into your WordPress installation:

```
wp-content/plugins/wp-appointments/
```

The plugin folder to copy is the inner `wp-appointments/` directory (the one containing `wp-appointments.php`), not the root of this repository.

### 2. Activate the plugin

Go to **Plugins** in wp-admin and activate **WP Appointments**. Activation creates the four database tables automatically.

### 3. Configure settings

Go to **Appointments → Settings** and fill in:

- **Notification Email** — where new booking requests are sent (defaults to the WordPress admin email)
- **Sender Name** — the `From:` name on all plugin emails (defaults to the site name)
- **Booking Page** — select the page where you will embed the widget (required for reschedule links in confirmation emails to work correctly)

### 4. Add your services

Go to **Appointments → Services** and add at least one service with a name, duration, and price.

### 5. Set your availability

Go to **Appointments → Availability** and configure your weekly schedule. You can set multiple open windows per day (e.g. 09:00–12:00 and 14:00–18:00) and block individual dates under the blocked slots section.

### 6. Embed the booking widget

See below.

---

## Embedding the widget

### Option A — Divi Visual Builder (recommended)

1. Open your booking page in the Divi Visual Builder.
2. Add a new row and insert a module.
3. Search for **Booking Widget** and select it.
4. Save and publish.

### Option B — Shortcode

Add `[wpappt_booking]` to any page using a Code module (Divi) or Shortcode block (Gutenberg).

> The widget has a max-width of 640 px and centres itself. A single-column Divi row with no sidebar works best.

See [`docs/embedding-the-widget.md`](docs/embedding-the-widget.md) for full details including colour customisation, or [`docs/smtp-configuration.md`](docs/smtp-configuration.md) for SMTP setup.

---

## Repository structure

```
wp-appointments/               ← plugin root (copy this into wp-content/plugins/)
│
├── wp-appointments.php        ← plugin header, constants, autoloader bootstrap
├── uninstall.php              ← drops all tables on plugin deletion
│
├── includes/
│   ├── class-plugin.php       ← singleton orchestrator, wires all subsystems
│   ├── class-activator.php    ← creates DB tables via dbDelta() on activation
│   ├── class-deactivator.php  ← deactivation cleanup
│   ├── class-autoloader.php   ← SPL autoloader (no Composer needed at runtime)
│   ├── class-rest-api.php     ← registers all WP REST API routes
│   ├── class-divi-module.php  ← ET_Builder_Module for Divi Visual Builder
│   │
│   ├── controllers/
│   │   ├── class-booking.php       ← POST /bookings handler
│   │   ├── class-reschedule.php    ← GET + POST /reschedule handler
│   │   └── class-admin-ajax.php    ← admin confirm/cancel/followup actions
│   │
│   ├── models/
│   │   ├── class-booking.php       ← CRUD for bookings + audit log
│   │   ├── class-service.php       ← CRUD for services
│   │   └── class-availability.php  ← CRUD for availability template + blocked slots
│   │
│   ├── services/
│   │   ├── class-availability.php  ← slot calculation engine
│   │   ├── class-email.php         ← all transactional email dispatch
│   │   ├── class-token.php         ← reschedule token generation + validation
│   │   ├── class-reminder.php      ← daily cron handler for appointment reminders
│   │   └── class-token-cleanup.php ← weekly cron handler to expire stale tokens
│   │
│   └── helpers/
│       ├── class-sanitizer.php     ← input sanitisation helpers
│       └── class-rate-limiter.php  ← transient-based fixed-window rate limiter
│
├── admin/
│   ├── class-admin.php                  ← menu registration, asset enqueueing
│   ├── class-bookings-list-table.php    ← WP_List_Table implementation
│   ├── class-settings-page.php          ← WP Settings API integration
│   └── views/
│       ├── bookings-list.php
│       ├── booking-detail.php
│       ├── services-page.php
│       └── availability-page.php
│
├── assets/
│   ├── css/
│   │   ├── admin.css            ← admin panel styles
│   │   └── booking-widget.css   ← frontend widget styles
│   ├── js/
│   │   ├── admin.js             ← admin panel scripts
│   │   └── booking-widget.js    ← self-contained IIFE booking widget
│   └── images/
│       └── wp-logo.png          ← plugin logo (used as admin menu icon)
│
├── languages/
│   ├── wp-appointments-nl_NL.po  ← Dutch translation source
│   └── wp-appointments-nl_NL.mo  ← compiled Dutch translation
│
└── templates/emails/
    ├── base.php                      ← shared HTML email shell
    ├── booking-received-customer.php
    ├── booking-received-admin.php
    ├── booking-confirmed.php
    ├── booking-cancelled.php
    ├── reschedule-customer.php
    ├── reschedule-admin.php
    ├── reminder-customer.php
    └── followup.php
```

**Outside the plugin folder:**

```
docs/
├── embedding-the-widget.md   ← how to embed the widget (Divi + shortcode)
├── smtp-configuration.md     ← SMTP setup examples for common providers
└── local-development.md      ← local dev setup

tests/
└── unit/                    ← PHPUnit test suite (Brain Monkey + Mockery)
```

---

## Development

### Running tests

Install dev dependencies (PHPUnit, Brain Monkey, Mockery):

```bash
composer install
```

Run the full test suite:

```bash
composer test
```

Run with coverage report:

```bash
composer test:coverage
```

Tests live in `tests/unit/` and are organised to mirror the `includes/` directory structure. The test bootstrap (`tests/bootstrap.php`) stubs all WordPress functions via Brain Monkey so no WordPress install is needed to run the tests.

### Class naming and autoloading

The plugin uses its own SPL autoloader — no Composer autoloader is needed at runtime. Classes follow this convention:

| Class name | File path |
|---|---|
| `WPAPPT_Plugin` | `includes/class-plugin.php` |
| `WPAPPT_Model_Booking` | `includes/models/class-booking.php` |
| `WPAPPT_Controller_Booking` | `includes/controllers/class-booking.php` |
| `WPAPPT_Service_Email` | `includes/services/class-email.php` |
| `WPAPPT_Helper_Sanitizer` | `includes/helpers/class-sanitizer.php` |
| `WPAPPT_Admin` | `admin/class-admin.php` |

The rule: strip `WPAPPT_`, lowercase, replace underscores with dashes. The first segment maps to a subdirectory; the remainder becomes the filename.

### REST API

Base URL: `/wp-json/wpappt/v1/`

| Method | Endpoint | Description | Auth |
|---|---|---|---|
| GET | `/services` | List active services | None |
| GET | `/availability?service_id=&date=` | Available slots for a date + service | None |
| POST | `/bookings` | Submit a new booking | Rate-limited; nonce sent by the widget |
| GET | `/reschedule?token=` | Validate a reschedule token | Token |
| POST | `/reschedule` | Apply a reschedule | Token; rate-limited |

The booking widget automatically includes a WordPress nonce (`X-WP-Nonce` header) with every POST request. Rate limiting (5 requests per 10 minutes per IP) is enforced server-side on the booking endpoint, and 10 per hour on the reschedule endpoints.


### Extending via action hooks

All significant events fire WordPress action hooks. Hook in from your theme's `functions.php` without modifying plugin files:

```php
// Run custom code when a new booking is submitted
add_action( 'wpappt_booking_created', function ( int $booking_id ): void {
    // e.g. push to a CRM, send an SMS
} );

// Run custom code when a booking is confirmed, cancelled, etc.
add_action( 'wpappt_booking_status_changed', function ( int $booking_id, string $new_status ): void {
    // $new_status is 'confirmed' or 'cancelled'
}, 10, 2 );
```


### PHP constants

Advanced behaviour can be overridden by defining constants in `wp-config.php` before the plugin loads:

| Constant | Default | Description |
|---|---|---|
| `WPAPPT_RATE_LIMIT` | `5` | Maximum booking submissions per window per IP |
| `WPAPPT_RATE_WINDOW` | `600` | Rate-limit window in seconds (default: 10 minutes) |
| `WPAPPT_TRUST_PROXY` | `false` | Set to `true` if the site sits behind a trusted reverse proxy/load balancer. When enabled, the `X-Forwarded-For` header is used to resolve the real client IP for rate limiting. **Leave `false` unless you control the proxy** — enabling it on a direct server allows clients to spoof their IP and bypass the rate limiter. |

```php
// wp-config.php
define( 'WPAPPT_TRUST_PROXY', true );  // only if behind a trusted reverse proxy
```

---

### Widget colour theming

The booking widget uses CSS custom properties. Override them in **Divi → Theme Options → Custom CSS** or **Appearance → Customize → Additional CSS**:

```css
#wpappt-booking-widget {
    --wpappt-primary:       #your-brand-colour;
    --wpappt-primary-dark:  #your-brand-colour-hover;
    --wpappt-primary-bg:    #your-brand-colour-tint;
}
```

---

## Documentation index

| Document | Contents |
|---|---|
| [`docs/embedding-the-widget.md`](docs/embedding-the-widget.md) | How to embed via Divi or shortcode, colour customisation |
| [`docs/smtp-configuration.md`](docs/smtp-configuration.md) | SMTP setup examples (Gmail, Mailgun, SendGrid, etc.) |
| [`docs/local-development.md`](docs/local-development.md) | Local dev environment setup |
