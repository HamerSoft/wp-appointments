# Build Plan: WP Appointments Plugin

## Build Order

### Step 1 — Plugin Bootstrap & Database
- Main plugin file (`wp-appointments.php`): constants, autoloader, hooks
- `uninstall.php`: drops all custom tables on uninstall
- `class-plugin.php`: singleton orchestrator
- `class-activator.php`: creates all four DB tables via `dbDelta()`
- `class-deactivator.php`: deactivation cleanup

### Step 2 — Model Classes
- `class-service.php`: CRUD for services table
- `class-availability.php`: CRUD for availability and blocked slots tables
- `class-booking.php`: CRUD for bookings table

### Step 3 — Admin Panel
- `class-admin.php`: menu registration, asset enqueueing
- Services page: add/edit/remove services
- Availability page: manage weekly template and blocked slots
- Settings page: admin email, sender name, booking page ID

### Step 4 — Availability Service
- `class-availability-service.php`: slot calculation logic
  - Resolve available slots for a given date + service duration
  - Factor in weekly template, blocked slots, and existing bookings

### Step 5 — REST API & Booking Controller
- `class-rest-api.php`: register all routes
- `class-booking-controller.php`: handle `POST /bookings`
  - Validate input, check slot availability, create booking, trigger emails

### Step 6 — Email Service & Templates
- `class-email-service.php`: one method per email event
- `templates/emails/base.php`: shared HTML shell
- Individual templates: received (customer + admin), confirmed, cancelled, reschedule (customer + admin)

### Step 7 — Token Service & Reschedule Endpoints
- `class-token-service.php`: generate, validate, and expire tokens
- `class-reschedule-controller.php`: handle `GET` and `POST /reschedule`

### Step 8 — Frontend JS Widget
- `booking-widget.js`: self-contained IIFE, multi-step form
  - Step 1: Service selection
  - Step 2: Date picker
  - Step 3: Time slot picker
  - Step 4: Customer details form
  - Step 5: Confirmation summary
  - Step 6: Success screen
- `booking-widget.css`: widget styles

### Step 9 — Divi Module & Shortcode Fallback
- `class-divi-module.php`: extends `ET_Builder_Module`, registers on `et_builder_ready`
- Shortcode `[wpappt_booking]`: renders same container div for non-Divi use

---

## Milestones

| Milestone | Steps | Outcome |
|---|---|---|
| Plugin installs cleanly | 1 | Tables created, no errors |
| Admin can configure services & availability | 2–3 | Ready to receive bookings |
| Bookings can be submitted | 4–5 | Full booking flow works via REST |
| Notifications fire correctly | 6 | All emails sent and received |
| Customers can self-reschedule | 7 | Token flow works end to end |
| Widget live on site | 8–9 | Embeddable in Divi or via shortcode |
