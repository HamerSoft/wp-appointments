# Security Backlog

Fixes identified in the 2026-04-11 security audit (`docs/security-audit.md`).

---

## 1. Rate limit bypass via X-Forwarded-For spoofing

**Severity:** Medium

**What:** `get_client_ip()` unconditionally trusts the `HTTP_X_FORWARDED_FOR` header, which is user-controlled. An attacker can rotate this value on every request to bypass rate limiting on the booking and reschedule endpoints.

**Implementation notes:**

- Add a constant `WPAPPT_TRUST_PROXY` that site owners define in `wp-config.php` if their server sits behind a known reverse proxy or load balancer. Default behaviour (constant absent) should fall straight through to `REMOTE_ADDR`.
- Update `get_client_ip()` in `WPAPPT_Controller_Reschedule` to only read `HTTP_X_FORWARDED_FOR` when the constant is defined and truthy:
  ```php
  public function get_client_ip(): string {
      if ( defined( 'WPAPPT_TRUST_PROXY' ) && WPAPPT_TRUST_PROXY ) {
          $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
          if ( $forwarded ) {
              $ip = trim( explode( ',', $forwarded )[0] );
              if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                  return $ip;
              }
          }
      }
      return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
  }
  ```
- `WPAPPT_Helper_Rate_Limiter` uses the IP string it is handed — no changes needed there.
- Document the constant in `docs/local-development.md` or a new `docs/configuration.md` so future maintainers know it exists.

---

## 2. HTML permitted in customer-submitted fields

**Severity:** Low

**What:** `injury_notes` and `comments` are sanitized with `wp_kses_post()`, which allows a limited subset of HTML tags. These fields contain free-text notes from customers — there is no use case for markup, and permitting it increases the stored attack surface unnecessarily.

**Implementation notes:**

- In `WPAPPT_Helper_Sanitizer::booking_input()`, replace `wp_kses_post` with `sanitize_textarea_field` for both fields:
  ```php
  'injury_notes' => sanitize_textarea_field( $raw['injury_notes'] ?? '' ),
  'comments'     => sanitize_textarea_field( $raw['comments']     ?? '' ),
  ```
- In `WPAPPT_Rest_Api::register_routes()`, update the `sanitize_callback` for both REST API args to match:
  ```php
  'injury_notes' => [ 'sanitize_callback' => 'sanitize_textarea_field', ... ],
  'comments'     => [ 'sanitize_callback' => 'sanitize_textarea_field', ... ],
  ```
- In `admin/views/booking-detail.php`, the output calls currently use `wp_kses_post()` for these two fields. Replace with `nl2br( esc_html( ... ) )` so line breaks are preserved without allowing any markup:
  ```php
  <td><?php echo nl2br( esc_html( $booking['injury_notes'] ) ); ?></td>
  ```
- No DB migration needed — `sanitize_textarea_field` produces plain text that fits the existing `TEXT` columns. Existing rows with HTML content will display as literal tags after this change; acceptable given this is an admin-only view and the volume of existing data is small.

---

## 3. Unauthenticated access to booking endpoint

**Severity:** Low

**What:** `POST /wpappt/v1/bookings` uses `permission_callback => '__return_true'`, meaning any script can submit bookings without a WordPress session or nonce. The rate limiter is the only control, and its effectiveness depends on fixing item 1 above.

**Implementation notes:**

- Add a honeypot field to the booking widget JS (a hidden `<input>` that is empty for real users and filled by bots). The REST controller rejects any submission where the honeypot field is non-empty:
  ```php
  // In WPAPPT_Controller_Booking::handle_create()
  if ( ! empty( $request->get_param( 'website' ) ) ) {
      return new \WP_REST_Response( [ 'message' => 'Invalid request.' ], 400 );
  }
  ```
  Register the arg in `WPAPPT_Rest_Api`:
  ```php
  'website' => [ 'required' => false, 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ],
  ```
  In the widget JS, add the hidden field to the form data but never populate it:
  ```js
  formData.website = ''; // honeypot — must stay empty
  ```
- The field should be visually hidden via CSS (`position: absolute; left: -9999px`) rather than `display: none` or `type="hidden"`, as some bots detect and skip `type="hidden"` fields.
- This is a lightweight, dependency-free control that complements the rate limiter (item 1) rather than replacing it. It does not affect legitimate users.
