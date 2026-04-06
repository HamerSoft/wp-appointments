# SMTP Configuration

The plugin sends transactional emails via WordPress's `wp_mail`. By default WordPress uses the server's PHP `mail()` function, which is often blocked or lands in spam. Configuring SMTP routes all outgoing mail through your own domain email instead.

## How it works

Use the email address that came with your domain registration (e.g. `bookings@yourdomain.com`) as the SMTP sender. Set up automatic forwarding on that mailbox to your personal inbox — you'll receive all booking notifications there without ever having to check the domain mailbox directly.

---

## Credential storage — resolution order

The plugin resolves each SMTP setting in this order, using the first value it finds:

| Setting | Sources |
|---|---|
| Host, port, encryption | Environment variable → `wp-config.php` constant → Settings page |
| **Username, password** | **Environment variable → `wp-config.php` constant only** |

Credentials are **never stored in the database**. There is no username or password field in the settings page.

---

## Option A: wp-config.php constants (recommended)

Add these above the `/* That's all, stop editing! */` line in `wp-config.php`. The credentials stay in a file on the server, not in the database.

```php
define( 'WPAPPT_SMTP_HOST',       'mail.yourdomain.com' ); // from your registrar/host
define( 'WPAPPT_SMTP_PORT',       587 );
define( 'WPAPPT_SMTP_ENCRYPTION', 'tls' );
define( 'WPAPPT_SMTP_USERNAME',   'bookings@yourdomain.com' );
define( 'WPAPPT_SMTP_PASSWORD',   'your-mailbox-password' );
```

> The SMTP host, port, and encryption values are provided by your domain registrar or hosting panel. Common examples: `mail.yourdomain.com` on port 587 with TLS.

---

## Option B: environment variables

Set these at the server or hosting level (via your hosting control panel or web server config). The values never appear in any file you manage directly.

```
WPAPPT_SMTP_HOST=mail.yourdomain.com
WPAPPT_SMTP_PORT=587
WPAPPT_SMTP_ENCRYPTION=tls
WPAPPT_SMTP_USERNAME=bookings@yourdomain.com
WPAPPT_SMTP_PASSWORD=your-mailbox-password
```

---

## Settings page

**Appointments → Settings → Outgoing Email (SMTP)** lets you configure the host, port, and encryption. These non-sensitive values can safely live in the database.

Username and password are not on the settings page — set them via one of the options above.

---

## Local development

In the Docker dev environment, all outgoing mail is intercepted by **MailHog** regardless of SMTP settings. No real emails are sent.

- View captured emails at **http://localhost:8025**
- MailHog is configured automatically by `bin/setup-wp.sh`
