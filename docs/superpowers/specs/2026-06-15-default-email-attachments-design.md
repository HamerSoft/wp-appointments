# Default Email Attachments per Language

**Date:** 2026-06-15

## Overview

Allow the admin to configure a default attachment per email template per language on the Email Templates page. When an email is sent without a manual per-booking attachment, the default is used. When a manual attachment is present, the default is skipped entirely.

## Scope

Applies to the 6 customer-facing email templates only:
- `booking_received_customer`
- `booking_confirmed`
- `booking_cancelled`
- `reschedule_customer`
- `reminder_customer`
- `followup`

The 2 admin-notification templates (`booking_received_admin`, `reschedule_admin`) do not support default attachments.

## Section 1: Data & Storage

### Registry flag

Each customer-facing template entry in `WPAPPT_Service_Email_Template_Store::get_registry()` gets `'customer_facing' => true`. The view and save handler use this flag to know which templates expose the attachment picker.

### Storage

One WP option per template+language:

```
wpappt_tpl_{slug}_{lang}_attachment_id   (integer, WP media attachment ID)
```

Example: `wpappt_tpl_booking_confirmed_en_attachment_id`.

Stored as `0` when unset. Follows the existing `wpappt_tpl_{slug}_{lang}_{field}` naming convention.

### New template store method

```php
public static function get_default_attachment_path(string $slug, string $lang): string
```

- Reads `wpappt_tpl_{slug}_{lang}_attachment_id` from WP options.
- Returns `''` if the option is 0.
- Calls `get_attached_file($id)` and checks `file_exists()`.
- Returns the server file path, or `''` if the file is missing or the attachment is invalid.

## Section 2: Email Templates UI

### Attachment picker row

Inside each customer-facing template's EN/NL tab panel, a "Default attachment" row appears below the existing text fields. It uses the same HTML pattern as the booking detail attachment picker:

- An "Attach file" button (`.wpappt-attach-btn`)
- A filename label (`.wpappt-attachment-name`)
- A clear button (`.wpappt-attachment-clear`)
- A hidden input: `name="wpappt_tpl[{slug}][{lang}][attachment_id]"` with the current stored ID as value

If an attachment is already stored, the filename is retrieved via `wp_basename(get_attached_file($id))` and shown on page load inside the `.wpappt-attachment-name` span, with the clear button visible.

### Asset enqueuing

`WPAPPT_Admin::enqueue_assets()` already enqueues `admin-booking-attachments.js` on the booking detail page. It must also enqueue `wp_media` and `admin-booking-attachments.js` on the email templates page (`appointments_page_wpappt-email-templates`).

### Save handler changes

`WPAPPT_Admin_Email_Templates_Page::save_posted_data()` handles `attachment_id` in a separate pass after the text-fields loop — only for templates where `$config['customer_facing'] ?? false` is true. Sanitized with `absint`.

```php
foreach ($registry as $slug => $config) {
    if (empty($config['customer_facing'])) continue;
    foreach (['en', 'nl'] as $lang) {
        $id = absint($input[$slug][$lang]['attachment_id'] ?? 0);
        update_option("wpappt_tpl_{$slug}_{$lang}_attachment_id", $id);
    }
}
```

## Section 3: Email Service

### New helper method

```php
private function resolve_attachments(string $slug, array $manual): array
```

- If `$manual` is non-empty, return `$manual` unchanged (manual replaces default).
- Otherwise, call `WPAPPT_Service_Email_Template_Store::get_default_attachment_path($slug, $this->get_lang())`.
- Return `[$path]` if path is non-empty, else `[]`.

### Changes to send methods

All 6 customer-facing send methods call `resolve_attachments()` before passing to `send()`:

| Method | Currently has `$attachments` param? | Change |
|---|---|---|
| `send_booking_received_customer` | No | Add call to `resolve_attachments('booking_received_customer', [])` |
| `send_booking_confirmed` | Yes | Replace direct pass with `resolve_attachments('booking_confirmed', $attachments)` |
| `send_booking_cancelled` | Yes | Replace direct pass with `resolve_attachments('booking_cancelled', $attachments)` |
| `send_reschedule_customer` | No | Add call to `resolve_attachments('reschedule_customer', [])` |
| `send_reminder_customer` | No | Add call to `resolve_attachments('reminder_customer', [])` |
| `send_followup` | Yes | Replace direct pass with `resolve_attachments('followup', $attachments)` |

## Section 4: Tests

### `EmailTemplateStoreTest.php` — `get_default_attachment_path`

- Returns `''` when option is not set (defaults to 0).
- Returns `''` when `get_attached_file` returns false.
- Returns `''` when the resolved file does not exist on disk.
- Returns the file path when the attachment ID is valid and the file exists.

### `EmailServiceTest.php` — `resolve_attachments`

- Returns `$manual` unchanged when `$manual` is non-empty (manual replaces default).
- Returns the default path in an array when `$manual` is empty and a default is configured.
- Returns `[]` when `$manual` is empty and no default is configured.
