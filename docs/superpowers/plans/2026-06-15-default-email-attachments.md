# Default Email Attachments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow the admin to set a default WP media attachment per customer-facing email template per language; the default is used when no manual per-booking attachment is provided, and skipped when one is.

**Architecture:** The template store gains a `customer_facing` registry flag and a `get_default_attachment_path()` resolver. The email service gains a private `resolve_attachments()` helper that enforces the "manual replaces default" rule and is called by all 6 customer-facing send methods. The email templates view gains a media picker row per language tab for customer-facing templates, and the save handler persists the attachment ID alongside the text fields.

**Tech Stack:** WordPress plugin (PHP 8.1+), PHPUnit + Brain\Monkey + Mockery for tests, WP media library (`wp.media`) for the picker UI.

---

## File Map

| File | Change |
|---|---|
| `wp-appointments/includes/services/class-email-template-store.php` | Add `customer_facing` flag to 6 templates; add `get_default_attachment_path()` |
| `wp-appointments/admin/class-admin.php` | Enqueue `wp_media` + attachment JS on email templates page |
| `wp-appointments/admin/views/email-templates-page.php` | Add attachment picker row inside each customer-facing EN/NL tab panel |
| `wp-appointments/admin/class-email-templates-page.php` | Save `attachment_id` separately after text fields loop |
| `wp-appointments/includes/services/class-email.php` | Add `resolve_attachments()`; update 6 customer-facing send methods |
| `tests/unit/Services/EmailTemplateStoreTest.php` | Tests for `get_default_attachment_path()` |
| `tests/unit/Services/EmailServiceTest.php` | Tests for `resolve_attachments()` behaviour via public send methods |

---

### Task 1: Template store — `customer_facing` flag + `get_default_attachment_path()`

**Files:**
- Modify: `wp-appointments/includes/services/class-email-template-store.php`
- Test: `tests/unit/Services/EmailTemplateStoreTest.php`

- [ ] **Step 1: Write failing tests**

Add to `EmailTemplateStoreTest.php` after the existing tests:

```php
// =========================================================================
// customer_facing flag
// =========================================================================

/** @test */
public function customer_facing_templates_have_flag_set_to_true(): void {
    $registry = WPAPPT_Service_Email_Template_Store::get_registry();
    $customer_slugs = [
        'booking_received_customer',
        'booking_confirmed',
        'booking_cancelled',
        'reschedule_customer',
        'reminder_customer',
        'followup',
    ];
    foreach ( $customer_slugs as $slug ) {
        $this->assertTrue(
            $registry[ $slug ]['customer_facing'] ?? false,
            "Expected customer_facing = true for {$slug}"
        );
    }
}

/** @test */
public function admin_templates_do_not_have_customer_facing_flag(): void {
    $registry = WPAPPT_Service_Email_Template_Store::get_registry();
    $this->assertEmpty( $registry['booking_received_admin']['customer_facing'] ?? null );
    $this->assertEmpty( $registry['reschedule_admin']['customer_facing'] ?? null );
}

// =========================================================================
// get_default_attachment_path
// =========================================================================

/** @test */
public function get_default_attachment_path_returns_empty_when_option_is_zero(): void {
    Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
        if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' ) {
            return 0;
        }
        return $default ?? '';
    } );

    $path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
        'booking_confirmed', 'en'
    );

    $this->assertSame( '', $path );
}

/** @test */
public function get_default_attachment_path_returns_empty_when_get_attached_file_returns_false(): void {
    Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
        if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' ) {
            return 42;
        }
        return $default ?? '';
    } );
    Functions\when( 'get_attached_file' )->justReturn( false );

    $path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
        'booking_confirmed', 'en'
    );

    $this->assertSame( '', $path );
}

/** @test */
public function get_default_attachment_path_returns_empty_when_file_does_not_exist_on_disk(): void {
    $missing = sys_get_temp_dir() . '/wpappt_nonexistent_' . uniqid() . '.pdf';

    Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
        if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' ) {
            return 42;
        }
        return $default ?? '';
    } );
    Functions\when( 'get_attached_file' )->justReturn( $missing );

    $path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
        'booking_confirmed', 'en'
    );

    $this->assertSame( '', $path );
}

/** @test */
public function get_default_attachment_path_returns_file_path_when_attachment_is_valid(): void {
    $tmp = tempnam( sys_get_temp_dir(), 'wpappt_' );

    Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) use ( $tmp ) {
        if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' ) {
            return 42;
        }
        return $default ?? '';
    } );
    Functions\when( 'get_attached_file' )->justReturn( $tmp );

    $path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
        'booking_confirmed', 'en'
    );

    unlink( $tmp );

    $this->assertSame( $tmp, $path );
}

/** @test */
public function get_default_attachment_path_returns_empty_for_nl_when_only_en_is_set(): void {
    Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
        // Only EN attachment is set; NL option returns 0.
        if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' ) return 42;
        if ( $key === 'wpappt_tpl_booking_confirmed_nl_attachment_id' ) return 0;
        return $default ?? '';
    } );

    $path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
        'booking_confirmed', 'nl'
    );

    $this->assertSame( '', $path );
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
docker compose exec php vendor/bin/phpunit tests/unit/Services/EmailTemplateStoreTest.php --filter "customer_facing|get_default_attachment_path" 2>&1
```

Expected: FAIL — `customer_facing` key not found, `get_default_attachment_path` method not found.

- [ ] **Step 3: Add `customer_facing` flag to the 6 customer templates in `get_registry()`**

In `wp-appointments/includes/services/class-email-template-store.php`, add `'customer_facing' => true` to each of the 6 customer-facing entries. Example for one entry (repeat for all 6):

```php
'booking_received_customer' => [
    'label'          => 'Booking Received (Customer)',
    'customer_facing' => true,
    'fields'         => [
        // ... existing fields unchanged ...
    ],
],
```

The full list of slugs that get the flag: `booking_received_customer`, `booking_confirmed`, `booking_cancelled`, `reschedule_customer`, `reminder_customer`, `followup`. Do NOT add it to `booking_received_admin` or `reschedule_admin`.

- [ ] **Step 4: Add `get_default_attachment_path()` to the template store**

Add this static method to `WPAPPT_Service_Email_Template_Store` after `get_texts()`:

```php
/**
 * Return the server file path of the default attachment for a template+language.
 *
 * Returns '' if no attachment is configured, the WP attachment is invalid,
 * or the file does not exist on disk.
 */
public static function get_default_attachment_path( string $slug, string $lang ): string {
    $id = (int) get_option( "wpappt_tpl_{$slug}_{$lang}_attachment_id", 0 );

    if ( $id <= 0 ) {
        return '';
    }

    $path = get_attached_file( $id );

    if ( ! $path || ! file_exists( $path ) ) {
        return '';
    }

    return $path;
}
```

- [ ] **Step 5: Run tests to confirm they pass**

```bash
docker compose exec php vendor/bin/phpunit tests/unit/Services/EmailTemplateStoreTest.php 2>&1
```

Expected: All tests PASS.

- [ ] **Step 6: Commit**

```bash
git add wp-appointments/includes/services/class-email-template-store.php tests/unit/Services/EmailTemplateStoreTest.php
git commit -m "feat: add customer_facing flag and get_default_attachment_path to template store"
```

---

### Task 2: Enqueue media picker assets on email templates page

**Files:**
- Modify: `wp-appointments/admin/class-admin.php`

- [ ] **Step 1: Update `enqueue_assets()` to load media + attachment JS on the email templates page**

In `wp-appointments/admin/class-admin.php`, find the `enqueue_assets()` method. After the block that enqueues media on the booking detail view, add:

```php
if ( 'appointments_page_wpappt-email-templates' === $hook ) {
    wp_enqueue_media();
    wp_enqueue_script(
        'wpappt-booking-attachments',
        WPAPPT_PLUGIN_URL . 'assets/js/admin-booking-attachments.js',
        [],
        WPAPPT_VERSION,
        true
    );
}
```

The existing block for booking detail looks like this (for reference — do not change it):

```php
if ( 'toplevel_page_wpappt-bookings' === $hook && 'view' === ( $_GET['action'] ?? '' ) ) {
    wp_enqueue_media();
    wp_enqueue_script(
        'wpappt-booking-attachments',
        WPAPPT_PLUGIN_URL . 'assets/js/admin-booking-attachments.js',
        [],
        WPAPPT_VERSION,
        true
    );
}
```

Add the new block immediately after the existing one.

- [ ] **Step 2: Commit**

```bash
git add wp-appointments/admin/class-admin.php
git commit -m "feat: enqueue media picker assets on email templates page"
```

---

### Task 3: Add attachment picker to email templates view

**Files:**
- Modify: `wp-appointments/admin/views/email-templates-page.php`

- [ ] **Step 1: Add the attachment picker row inside each customer-facing tab panel**

In `wp-appointments/admin/views/email-templates-page.php`, locate this exact line (it appears inside the `foreach ( [ 'en', 'nl' ] as $lang )` loop):

```php
					<?php endforeach; ?>
					</div>
```

That `endforeach` closes the `foreach ( $config['fields'] ... )` loop, and the `</div>` closes `.wpappt-tab-panel`. Insert the following block **between** the `endforeach` and the `</div>`:

```php
					<?php endforeach; ?>

					<?php if ( ! empty( $config['customer_facing'] ) ) :
						$att_id       = (int) get_option( "wpappt_tpl_{$slug}_{$lang}_attachment_id", 0 );
						$att_path     = $att_id > 0 ? get_attached_file( $att_id ) : false;
						$att_filename = ( $att_path && file_exists( $att_path ) ) ? wp_basename( $att_path ) : '';
					?>
					<p class="wpappt-attachment-picker">
						<label><strong><?php esc_html_e( 'Default attachment', 'wp-appointments' ); ?></strong></label><br>
						<button type="button" class="button wpappt-attach-btn">
							<?php esc_html_e( 'Attach file', 'wp-appointments' ); ?>
						</button>
						<span class="wpappt-attachment-name"
						      style="<?php echo $att_filename ? '' : 'display:none;'; ?>">
							<?php echo esc_html( $att_filename ); ?>
						</span>
						<button type="button" class="wpappt-attachment-clear"
						        style="<?php echo $att_filename ? '' : 'display:none;'; ?>"
						        aria-label="<?php esc_attr_e( 'Remove attachment', 'wp-appointments' ); ?>">&#x2715;</button>
						<input type="hidden"
						       name="wpappt_tpl[<?php echo esc_attr( $slug ); ?>][<?php echo esc_attr( $lang ); ?>][attachment_id]"
						       class="wpappt-attachment-id"
						       value="<?php echo esc_attr( (string) $att_id ); ?>">
					</p>
					<?php endif; ?>
					</div>
```

Nothing else in the file changes.

- [ ] **Step 2: Commit**

```bash
git add wp-appointments/admin/views/email-templates-page.php
git commit -m "feat: add default attachment picker to customer-facing email template panels"
```

---

### Task 4: Save handler — persist `attachment_id`

**Files:**
- Modify: `wp-appointments/admin/class-email-templates-page.php`

- [ ] **Step 1: Add attachment ID saving to `save_posted_data()`**

In `wp-appointments/admin/class-email-templates-page.php`, add a second loop after the existing text-fields loop inside `save_posted_data()`. The current method ends like this:

```php
public function save_posted_data( array $input ): void {
    $registry = WPAPPT_Service_Email_Template_Store::get_registry();
    $langs    = [ 'en', 'nl' ];

    foreach ( $registry as $slug => $config ) {
        if ( ! isset( $input[ $slug ] ) ) {
            continue;
        }
        foreach ( $langs as $lang ) {
            if ( ! isset( $input[ $slug ][ $lang ] ) ) {
                continue;
            }
            foreach ( array_keys( $config['fields'] ) as $field ) {
                $value     = $input[ $slug ][ $lang ][ $field ] ?? '';
                $sanitized = 'subject' === $field
                    ? sanitize_text_field( $value )
                    : sanitize_textarea_field( $value );
                update_option(
                    "wpappt_tpl_{$slug}_{$lang}_{$field}",
                    $sanitized
                );
            }
        }
    }
}
```

Add the following block immediately after the closing `}` of the outer `foreach`:

```php
    // Save default attachment IDs for customer-facing templates.
    foreach ( $registry as $slug => $config ) {
        if ( empty( $config['customer_facing'] ) ) {
            continue;
        }
        foreach ( $langs as $lang ) {
            $id = absint( $input[ $slug ][ $lang ]['attachment_id'] ?? 0 );
            update_option( "wpappt_tpl_{$slug}_{$lang}_attachment_id", $id );
        }
    }
```

- [ ] **Step 2: Commit**

```bash
git add wp-appointments/admin/class-email-templates-page.php
git commit -m "feat: save default attachment IDs from email templates form"
```

---

### Task 5: Email service — `resolve_attachments()` + update send methods

**Files:**
- Modify: `wp-appointments/includes/services/class-email.php`
- Test: `tests/unit/Services/EmailServiceTest.php`

- [ ] **Step 1: Write failing tests**

Add to `EmailServiceTest.php` in a new `// resolve_attachments` section:

```php
// =========================================================================
// Default attachment fallback (resolve_attachments behaviour)
// =========================================================================

/** @test */
public function manual_attachment_is_used_and_default_is_not_fetched_when_manual_is_set(): void {
    $booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
    $service_model = \Mockery::mock( WPAPPT_Model_Service::class );

    $booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
    $service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

    Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
        if ( $key === 'wpappt_email_language' )  return 'en';
        if ( $key === 'wpappt_sender_name' )     return 'Test Spa';
        if ( $key === 'wpappt_admin_email' )     return 'admin@example.com';
        return $default ?? '';
    } );

    // get_attached_file must never be called — manual replaces default.
    Functions\expect( 'get_attached_file' )->never();

    $capturedAttachments = null;
    Functions\when( 'wp_mail' )->alias(
        function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedAttachments ): bool {
            $capturedAttachments = $attachments;
            return true;
        }
    );

    $this->makeService( $booking_model, $service_model )
         ->send_booking_confirmed( 1, '', [ '/tmp/manual.pdf' ] );

    $this->assertSame( [ '/tmp/manual.pdf' ], $capturedAttachments );
}

/** @test */
public function default_attachment_is_used_when_no_manual_attachment_is_provided(): void {
    $tmp = tempnam( sys_get_temp_dir(), 'wpappt_default_' );

    $booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
    $service_model = \Mockery::mock( WPAPPT_Model_Service::class );

    $booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
    $service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

    Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) use ( $tmp ) {
        if ( $key === 'wpappt_email_language' )                              return 'en';
        if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' )     return 99;
        if ( $key === 'wpappt_sender_name' )                                 return 'Test Spa';
        if ( $key === 'wpappt_admin_email' )                                 return 'admin@example.com';
        return $default ?? '';
    } );
    Functions\when( 'get_attached_file' )->justReturn( $tmp );

    $capturedAttachments = null;
    Functions\when( 'wp_mail' )->alias(
        function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedAttachments ): bool {
            $capturedAttachments = $attachments;
            return true;
        }
    );

    $this->makeService( $booking_model, $service_model )
         ->send_booking_confirmed( 1 );

    unlink( $tmp );

    $this->assertSame( [ $tmp ], $capturedAttachments );
}

/** @test */
public function no_attachment_is_used_when_neither_manual_nor_default_is_configured(): void {
    $booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
    $service_model = \Mockery::mock( WPAPPT_Model_Service::class );

    $booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
    $service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

    Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) {
        if ( $key === 'wpappt_email_language' )                              return 'en';
        if ( $key === 'wpappt_tpl_booking_confirmed_en_attachment_id' )     return 0;
        if ( $key === 'wpappt_sender_name' )                                 return 'Test Spa';
        if ( $key === 'wpappt_admin_email' )                                 return 'admin@example.com';
        return $default ?? '';
    } );

    $capturedAttachments = null;
    Functions\when( 'wp_mail' )->alias(
        function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedAttachments ): bool {
            $capturedAttachments = $attachments;
            return true;
        }
    );

    $this->makeService( $booking_model, $service_model )
         ->send_booking_confirmed( 1 );

    $this->assertSame( [], $capturedAttachments );
}

/** @test */
public function default_attachment_is_used_for_reminder_which_has_no_manual_attachment(): void {
    $tmp = tempnam( sys_get_temp_dir(), 'wpappt_reminder_' );

    $booking_model = \Mockery::mock( WPAPPT_Model_Booking::class );
    $service_model = \Mockery::mock( WPAPPT_Model_Service::class );

    $booking_model->shouldReceive( 'find' )->andReturn( $this->fakeBooking() );
    $service_model->shouldReceive( 'find' )->andReturn( $this->fakeService() );

    Functions\when( 'get_option' )->alias( function ( string $key, $default = null ) use ( $tmp ) {
        if ( $key === 'wpappt_email_language' )                             return 'en';
        if ( $key === 'wpappt_tpl_reminder_customer_en_attachment_id' )    return 55;
        if ( $key === 'wpappt_sender_name' )                                return 'Test Spa';
        if ( $key === 'wpappt_admin_email' )                                return 'admin@example.com';
        return $default ?? '';
    } );
    Functions\when( 'get_attached_file' )->justReturn( $tmp );

    $capturedAttachments = null;
    Functions\when( 'wp_mail' )->alias(
        function ( $to, $subject, $body, $headers, $attachments = [] ) use ( &$capturedAttachments ): bool {
            $capturedAttachments = $attachments;
            return true;
        }
    );

    $this->makeService( $booking_model, $service_model )
         ->send_reminder_customer( 1 );

    unlink( $tmp );

    $this->assertSame( [ $tmp ], $capturedAttachments );
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
docker compose exec php vendor/bin/phpunit tests/unit/Services/EmailServiceTest.php --filter "manual_attachment_is_used|default_attachment_is_used|no_attachment_is_used" 2>&1
```

Expected: FAIL — `resolve_attachments` method not found, or attachment args not matching.

- [ ] **Step 3: Add `resolve_attachments()` helper to `WPAPPT_Service_Email`**

In `wp-appointments/includes/services/class-email.php`, add this private method after `get_lang()`:

```php
/**
 * Apply the "manual replaces default" attachment rule.
 *
 * If $manual is non-empty, return it unchanged.
 * Otherwise, look up the default attachment path for $slug + current language
 * and return it in a one-element array, or [] if nothing is configured.
 *
 * @param  string[] $manual Server file paths supplied per-booking.
 * @return string[]
 */
private function resolve_attachments( string $slug, array $manual ): array {
    if ( ! empty( $manual ) ) {
        return $manual;
    }

    $path = WPAPPT_Service_Email_Template_Store::get_default_attachment_path(
        $slug,
        $this->get_lang()
    );

    return $path !== '' ? [ $path ] : [];
}
```

- [ ] **Step 4: Update the 6 customer-facing send methods**

Replace the `$attachments` argument passed to `$this->send()` in each method with a call to `resolve_attachments()`.

**`send_booking_received_customer()`** — currently passes no attachments. Change the `$this->send()` call:

```php
public function send_booking_received_customer( int $booking_id ): bool {
    $data = $this->load_booking_data( $booking_id );
    if ( null === $data ) {
        return false;
    }
    $this->apply_template_texts( $data, 'booking_received_customer' );
    return $this->send(
        $data['booking']['customer_email'],
        $data['subject'],
        'booking-received-customer',
        $data,
        $this->resolve_attachments( 'booking_received_customer', [] )
    );
}
```

**`send_booking_confirmed()`** — currently passes `$attachments`. Change to:

```php
public function send_booking_confirmed( int $booking_id, string $reschedule_link = '', array $attachments = [] ): bool {
    $data = $this->load_booking_data( $booking_id );
    if ( null === $data ) {
        return false;
    }
    $data['reschedule_link'] = $reschedule_link;
    $this->apply_template_texts( $data, 'booking_confirmed' );
    return $this->send(
        $data['booking']['customer_email'],
        $data['subject'],
        'booking-confirmed',
        $data,
        $this->resolve_attachments( 'booking_confirmed', $attachments )
    );
}
```

**`send_booking_cancelled()`** — currently passes `$attachments`. Change to:

```php
public function send_booking_cancelled( int $booking_id, array $attachments = [] ): bool {
    $data = $this->load_booking_data( $booking_id );
    if ( null === $data ) {
        return false;
    }
    $this->apply_template_texts( $data, 'booking_cancelled' );
    return $this->send(
        $data['booking']['customer_email'],
        $data['subject'],
        'booking-cancelled',
        $data,
        $this->resolve_attachments( 'booking_cancelled', $attachments )
    );
}
```

**`send_reschedule_customer()`** — currently passes no attachments. Change to:

```php
public function send_reschedule_customer( int $booking_id, string $reschedule_link = '' ): bool {
    $data = $this->load_booking_data( $booking_id );
    if ( null === $data ) {
        return false;
    }
    $data['reschedule_link'] = $reschedule_link;
    $this->apply_template_texts( $data, 'reschedule_customer' );
    return $this->send(
        $data['booking']['customer_email'],
        $data['subject'],
        'reschedule-customer',
        $data,
        $this->resolve_attachments( 'reschedule_customer', [] )
    );
}
```

**`send_reminder_customer()`** — currently passes no attachments. Change to:

```php
public function send_reminder_customer( int $booking_id ): bool {
    $data = $this->load_booking_data( $booking_id );
    if ( null === $data ) {
        return false;
    }
    $this->apply_template_texts( $data, 'reminder_customer' );
    return $this->send(
        $data['booking']['customer_email'],
        $data['subject'],
        'reminder-customer',
        $data,
        $this->resolve_attachments( 'reminder_customer', [] )
    );
}
```

**`send_followup()`** — currently passes `$attachments`. Change to:

```php
public function send_followup( int $booking_id, string $message, array $attachments = [] ): bool {
    $data = $this->load_booking_data( $booking_id );
    if ( null === $data ) {
        return false;
    }
    $data['message'] = nl2br( esc_html( $message ) );
    $this->apply_template_texts( $data, 'followup' );
    return $this->send(
        $data['booking']['customer_email'],
        $data['subject'],
        'followup',
        $data,
        $this->resolve_attachments( 'followup', $attachments )
    );
}
```

- [ ] **Step 5: Run the full test suite**

```bash
docker compose exec php vendor/bin/phpunit 2>&1
```

Expected: All tests PASS. The two existing tests `send_booking_confirmed_passes_empty_attachments_by_default` and `send_booking_confirmed_passes_attachments_as_5th_arg_to_wp_mail` should still pass because the setUp `get_option` stub returns a non-numeric string which `absint()` converts to 0, so `get_default_attachment_path` returns ''.

- [ ] **Step 6: Commit**

```bash
git add wp-appointments/includes/services/class-email.php tests/unit/Services/EmailServiceTest.php
git commit -m "feat: add resolve_attachments helper and wire default attachments into all customer email sends"
```
