# Security Audit — WP Appointments Plugin

**Date:** 2026-04-11
**Scope:** Full plugin codebase (`wp-appointments/`)
**Method:** Static code review cross-referenced against the OWASP Top 10, WPScan vulnerability database, Wordfence, and Patchstack's 2024/2025 WordPress vulnerability reports.

---

## Executive Summary

The plugin has a strong security baseline. All of the most commonly exploited WordPress plugin vulnerabilities — SQL injection, XSS, CSRF, missing capability checks, and insecure file inclusion — are handled correctly. Three low-to-medium issues were identified, none of which expose customer data or allow privilege escalation under normal server configuration.

| Severity | Count |
|---|---|
| Medium | 1 |
| Low | 2 |
| Informational | 1 |
| Pass | 12 |

---

## Findings

---

### MEDIUM — Rate Limit Bypass via X-Forwarded-For Spoofing

**Affected files:** `includes/controllers/class-reschedule.php:242–253`, `includes/helpers/class-rate-limiter.php`

**Description:**
Both the booking submission endpoint and the reschedule endpoints derive the client IP from `HTTP_X_FORWARDED_FOR` before falling back to `REMOTE_ADDR`. This header is user-controlled and is only trustworthy when the server sits behind a known reverse proxy or load balancer.

An attacker submitting a booking or enumerating reschedule tokens can rotate `X-Forwarded-For` values on each request, effectively resetting the rate-limit counter and making it non-functional.

```php
// class-reschedule.php — current
$forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
if ( $forwarded ) {
    $ip = trim( explode( ',', $forwarded )[0] );
    if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
        return $ip;  // ← accepts any client-supplied IP
    }
}
```

**Recommendation:**
Only trust `X-Forwarded-For` when the plugin is configured to do so. Add a constant that the site owner sets in `wp-config.php` if they know their infrastructure uses a proxy:

```php
// wp-config.php (site owner opts in)
define( 'WPAPPT_TRUST_PROXY', true );

// class-reschedule.php — safer approach
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

---

### LOW — HTML Permitted in Customer-Submitted Fields

**Affected files:** `includes/helpers/class-sanitizer.php:29–30`, `includes/class-rest-api.php:73–74`

**Description:**
The `injury_notes` and `comments` fields are sanitized with `wp_kses_post()` both in the sanitizer helper and as the REST API `sanitize_callback`. This allows a limited set of HTML tags (paragraphs, links, bold, etc.) in fields where plain text is sufficient and appropriate.

While output in the admin views is correctly escaped with `wp_kses_post()` (preventing XSS), there is no practical reason to permit HTML in a massage therapist's injury notes or booking comments. Permitting markup increases the stored attack surface and the visual confusion risk for the admin.

**Recommendation:**
Replace `wp_kses_post` with `sanitize_textarea_field` for both fields:

```php
// class-sanitizer.php
'injury_notes' => sanitize_textarea_field( $raw['injury_notes'] ?? '' ),
'comments'     => sanitize_textarea_field( $raw['comments']     ?? '' ),
```

Update the REST API args to match:

```php
'injury_notes' => [ 'sanitize_callback' => 'sanitize_textarea_field', ... ],
'comments'     => [ 'sanitize_callback' => 'sanitize_textarea_field', ... ],
```

The admin detail view already uses `wp_kses_post()` to display these fields, which can be downgraded to `esc_html( nl2br( $booking['injury_notes'] ) )` after the sanitizer change.

---

### LOW — Booking Endpoint Accepts Unauthenticated Requests Without a Nonce

**Affected files:** `includes/class-rest-api.php:62–76`

**Description:**
`POST /wpappt/v1/bookings` has `permission_callback => '__return_true'` and performs no WordPress nonce check. The inline comment states "Nonce (X-WP-Nonce) validated automatically by WP REST middleware," but this is only true for authenticated requests — the REST middleware only enforces the nonce when a logged-in session is present.

In practice, any script on the internet can POST valid-looking bookings to the endpoint without any session or nonce, subject only to the rate limiter (which is itself bypassable per the Medium finding above).

This is partially a design necessity — the booking widget is embedded on a public page — but the risk is worth documenting. The current rate limit of 5 submissions per 10 minutes per IP is the primary control.

**Recommendation:**
No code change required if the rate limiter is hardened per the Medium finding. Optionally consider adding a honeypot field in the booking widget (a hidden input that bots fill but humans don't) as a lightweight spam filter complementary to rate limiting. This lives in the frontend widget rather than the plugin PHP.

---

### INFORMATIONAL — No CAPTCHA or Bot-Detection on the Booking Form

**Description:**
There is no CAPTCHA or JavaScript challenge on the booking widget. A bot that respects the rate limiter and spoofs its IP (see Medium finding) can make continuous fake bookings, filling the admin's inbox and the database with junk.

**Recommendation:**
This is an acceptable trade-off for usability at low traffic. If spam submissions become a real problem, a simple, dependency-free option is Cloudflare Turnstile (free tier), which requires only a small JS snippet and a single server-side HTTP call to verify. No WordPress plugin dependency needed.

---

## Passing Controls

The following areas were reviewed and found to be implemented correctly.

---

### SQL Injection — PASS

All database queries use `$wpdb->prepare()` with typed placeholders (`%d`, `%s`). The `ORDER BY` clause in `find_all()` is whitelisted against `SORTABLE_COLUMNS` before being interpolated, preventing ORDER BY injection. No raw `$_GET`/`$_POST` values reach a query string.

---

### Cross-Site Scripting (XSS) — PASS

Every output point in admin views uses the correct escaping function:
- `esc_html()` for plain text
- `esc_attr()` for HTML attributes
- `esc_url()` for URLs
- `wp_kses_post()` for fields that store the (currently over-broad) HTML subset
- `esc_textarea()` for the admin notes textarea

No raw `echo` of user-supplied data was found anywhere in the template files.

---

### CSRF / Nonce + Capability Checks — PASS

Every `admin-post.php` handler:
1. Calls `check_admin_referer()` with an action-specific nonce tied to the booking ID (e.g. `wpappt_confirm_booking_{$id}`).
2. Calls `require_capability()` which verifies `current_user_can('manage_options')`.

Both checks are always present — neither is used as a substitute for the other, which is the most common failure pattern in WordPress plugins. The nonce and the capability check are complementary.

---

### Insecure Direct Object Reference (IDOR) — PASS

Admin handlers accept a `booking_id` POST parameter but always gate access with the `manage_options` capability check before acting on it. There is no endpoint where a lower-privileged or unauthenticated user can read or modify an arbitrary booking by ID.

The public reschedule endpoint (`GET /reschedule`) does return booking data, but access is gated by a valid 64-character cryptographically random token — not by the booking ID directly.

---

### File Inclusion — PASS

Template files are included using only paths constructed from the hardcoded `WPAPPT_PLUGIN_DIR` constant plus a fixed string (e.g. `'templates/emails/' . $template . '.php'`). The `$template` variable is always set by internal application code and never derives from user input. Path traversal via `../` is not possible.

All PHP files begin with `if ( ! defined( 'ABSPATH' ) ) { exit; }` to prevent direct HTTP access.

---

### Information Disclosure via REST API — PASS

The `format_booking()` method in `class-reschedule.php` explicitly whitelists the fields returned to the public reschedule endpoint. Sensitive fields intentionally excluded from the response:
- `reschedule_token` (the stored hash)
- `token_expires_at`
- `customer_email`
- `customer_phone`
- `injury_notes`
- `comments`
- `admin_notes`

---

### Rescue Token Security — PASS

The reschedule token design is robust:
- 32 bytes (`bin2hex(random_bytes(32))`) = 256 bits of entropy. Brute force is not feasible.
- Only the SHA-256 hash is stored in the database; the raw token travels only in email.
- `hash_equals()` prevents timing side-channel attacks on the comparison.
- Tokens expire after 72 hours.
- Tokens are nulled immediately on booking cancellation.
- Tokens are rotated (consumed and replaced) after each reschedule use, invalidating forwarded or cached links.
- The reschedule endpoint is rate-limited to 10 requests per hour per IP, deterring enumeration.

---

### Double-Booking Race Condition — PASS

The booking creation path wraps the availability check and INSERT in an InnoDB transaction with `SELECT ... FOR UPDATE`, locking the relevant rows during the check. A concurrent submission for the same slot will block until the first transaction commits, then fail with a `409 Conflict` rather than inserting a duplicate.

---

### Email Header Injection — PASS

The `From:` header value is built from the admin-configured sender name, not from any customer-supplied input. The sender name is passed through `WPAPPT_Helper_Sanitizer::email_header_value()` which strips all CR and LF characters (`\r`, `\n`), preventing header injection regardless of what the admin has stored in the option.

---

### Privilege Escalation via Admin AJAX — PASS

All admin actions are handled via `admin-post.php` hooks (not `wp_ajax_nopriv_*`), meaning they are only reachable by logged-in users. The `require_capability()` guard additionally enforces the `manage_options` capability, so a Subscriber or Editor account cannot trigger any plugin state change.

---

### Rate Limiting — PASS (with caveat)

Rate limiting is implemented on both the booking creation endpoint (5 per 10 minutes per IP) and the reschedule endpoints (10 per hour per IP) using WordPress transients. The implementation is correct. The caveat is the X-Forwarded-For spoofing issue described in the Medium finding above.

---

### Input Sanitization — PASS

A centralized `WPAPPT_Helper_Sanitizer` class is used consistently throughout all controllers. REST API route definitions declare `sanitize_callback` for every argument. No handler reads from `$_POST`/`$_GET` and passes the value directly to a model or template without going through a sanitizer first.

---

## Summary

```
┌─────────────────────────────────────────────────────────┬──────────┐
│ Vulnerability Category                                  │ Result   │
├─────────────────────────────────────────────────────────┼──────────┤
│ SQL Injection                                           │ PASS     │
│ Cross-Site Scripting (XSS)                              │ PASS     │
│ CSRF / Nonce verification                               │ PASS     │
│ Capability checks (privilege escalation)                │ PASS     │
│ Insecure Direct Object Reference (IDOR)                 │ PASS     │
│ Local / Remote File Inclusion                           │ PASS     │
│ Information disclosure via REST API                     │ PASS     │
│ Reschedule token security                               │ PASS     │
│ Double-booking race condition                           │ PASS     │
│ Email header injection                                  │ PASS     │
│ Privilege escalation via admin-ajax                     │ PASS     │
│ Rate limiting                                           │ PASS *   │
│ Input sanitization                                      │ PASS     │
├─────────────────────────────────────────────────────────┼──────────┤
│ Rate limit bypass via X-Forwarded-For spoofing          │ MEDIUM   │
│ HTML permitted in injury_notes / comments fields        │ LOW      │
│ Unauthenticated access to booking endpoint (by design)  │ LOW      │
│ No CAPTCHA / bot detection                              │ INFO     │
└─────────────────────────────────────────────────────────┴──────────┘
* Rate limiting logic is correct but effectiveness depends on resolving
  the X-Forwarded-For finding.
```

---

## Recommended Actions (Priority Order)

1. **[Medium]** Fix `get_client_ip()` to only trust `X-Forwarded-For` when `WPAPPT_TRUST_PROXY` is defined — prevents rate limit bypass.
2. **[Low]** Change `injury_notes` and `comments` sanitization from `wp_kses_post` to `sanitize_textarea_field` — removes unnecessary HTML from customer-submitted fields.
3. **[Informational]** Consider a honeypot field in the booking widget as a lightweight spam deterrent once the rate limiter is hardened.
