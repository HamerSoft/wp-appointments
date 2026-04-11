# Refactoring Backlog

Code quality improvements that do not add new features. Identified after the security audit pass.

---

## ~~1. Admin notice strings not translated~~ ✓ Done

**What:** Every message string in `WPAPPT_Admin::display_notices()` (`admin/class-admin.php:131–143`) is a hardcoded English literal with no `__()` wrapper. The rest of the plugin uses `__()` consistently throughout.

**Why:** This is a real i18n bug. Any future translation of the plugin would leave all admin feedback messages in English.

**Implementation notes:**

- Wrap each string value in `__( '...', 'wp-appointments' )`:
  ```php
  'booking_confirmed' => [ 'success', __( 'Booking confirmed.', 'wp-appointments' ) ],
  'booking_cancelled' => [ 'success', __( 'Booking cancelled.', 'wp-appointments' ) ],
  // … etc.
  ```
- No structural changes needed — only the string literals change.

---

## ~~2. REST API endpoint handlers create dependencies inline~~ ✓ Done

**What:** `WPAPPT_Rest_Api::get_services()` instantiates `new WPAPPT_Model_Service()` directly inside the method body, and `get_availability()` does the same with `new WPAPPT_Service_Availability()`. The booking and reschedule endpoints avoid this by delegating to injected controllers (`WPAPPT_Controller_Booking`, `WPAPPT_Controller_Reschedule`), making them testable. These two endpoints are not, and have no unit tests as a result.

**Why:** Inconsistency in the codebase and an untested surface area. Applying the same pattern that already exists for the other two endpoints brings the whole REST layer to the same standard.

**Implementation notes:**

- Add constructor injection to `WPAPPT_Rest_Api`:
  ```php
  public function __construct(
      ?WPAPPT_Model_Service        $service_model        = null,
      ?WPAPPT_Service_Availability $availability_service = null
  ) {
      $this->service_model        = $service_model        ?? new WPAPPT_Model_Service();
      $this->availability_service = $availability_service ?? new WPAPPT_Service_Availability();
  }
  ```
- Remove the inline `new` calls from `get_services()` and `get_availability()` and use the injected properties instead.
- Update `WPAPPT_Rest_Api` instantiation in `class-plugin.php` (or wherever it is wired up) — no args needed since defaults kick in.
- Add unit tests for both endpoints covering the happy path and edge cases (e.g. no active services, invalid date).

---

## 3. Sanitization defined in two places — drift risk

**What:** `WPAPPT_Helper_Sanitizer::booking_input()` is the canonical sanitizer for booking fields, but `WPAPPT_Rest_Api::register_routes()` also declares a `sanitize_callback` for each of the same fields in the REST arg definitions. Both layers produce identical output, making the REST callbacks redundant. This already caused a two-file change when switching `injury_notes` and `comments` from `wp_kses_post` to `sanitize_textarea_field`.

**Why:** Two sources of truth for the same logic will drift. The next field-level sanitization change will again require edits in two files, with no compiler or linter to catch a missed update.

**Implementation notes:**

- Remove the `sanitize_callback` entries from the REST arg definitions for all booking fields that are covered by `WPAPPT_Helper_Sanitizer::booking_input()`. Keep `type`, `required`, and `default` — those are handled by the REST framework and are still valuable.
- `WPAPPT_Helper_Sanitizer::booking_input()` in `process_create()` becomes the single sanitization point.
- The `service_id` arg can keep `'sanitize_callback' => 'absint'` since it is a simple cast that the sanitizer also does — or remove it too for full consistency.
- Verify existing tests still pass; no new behaviour is introduced.

---

## 4. `get_client_ip()` wrappers in controllers are redundant

**What:** After the security fix (item 1 of the security backlog), both `WPAPPT_Controller_Booking::get_client_ip()` and `WPAPPT_Controller_Reschedule::get_client_ip()` are identical one-liners:
```php
public function get_client_ip(): string {
    return WPAPPT_Helper_Rate_Limiter::get_client_ip();
}
```
They exist only because the original logic lived there. The logic now belongs to `WPAPPT_Helper_Rate_Limiter`.

**Why:** Dead-weight indirection. A reader following the call chain has to open an extra method to discover it does nothing but forward. Removing the wrappers makes the dependency explicit at the call site.

**Implementation notes:**

- In `WPAPPT_Controller_Booking::process_create()`, replace `$this->get_client_ip()` with `WPAPPT_Helper_Rate_Limiter::get_client_ip()` and delete the wrapper method.
- Do the same in `WPAPPT_Controller_Reschedule::process_reschedule()`.
- Verify tests still pass — the rate-limiting behaviour is unchanged.
