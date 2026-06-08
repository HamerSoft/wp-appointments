# SMTP Configuration

The plugin sends transactional emails via WordPress's `wp_mail`. By default WordPress uses the server's PHP `mail()` function. Depending on your host this is either perfectly fine (see below) or blocked/spam-prone, in which case configuring SMTP routes outgoing mail through your own domain email instead.

## Do you even need SMTP? (start here)

**If your website and your domain mailbox are hosted with the same provider, you probably don't need SMTP at all.**

In that setup the server's built-in PHP `mail()` already hands mail to the provider's local mail system, which trusts mail from its own hosting account. There's no external connection to make, so **no host, no port, no encryption, and — importantly — no username or password are required.** It's the simplest and fastest path, and it avoids storing a mailbox password anywhere.

To use it: **leave the SMTP Host field blank** in **Appointments → Settings → Outgoing Email (SMTP)** (and remove any `WPAPPT_SMTP_*` constants from `wp-config.php`). With no host configured, the plugin leaves WordPress on its default `mail()` path.

> Note on settings precedence: host/port/encryption resolve as Environment variable → `wp-config.php` constant → Settings page. Clearing the constants is **not** enough on its own — if the Settings-page host is still filled in, it keeps driving the SMTP path. Clear the host in **both** places to fall back to `mail()`.

The only thing that still matters is the **`From` address** — keep it on your real domain (e.g. `noreply@yourdomain.com`) so the provider's SPF record vouches for the mail and it isn't flagged as spam. That's a sender *identity*, not a login.

> **Worked example — Hostnet:** the site and the `manawamassages.nl` mailbox both live on Hostnet. Outbound connections to the public `smtp.hostnet.nl:587` are firewalled (they time out), so SMTP fails. Leaving the SMTP Host blank to use native `mail()` works instantly and needs no credentials.

### Are credentials still valuable?

Yes — SMTP credentials are genuinely useful when you need to send through an *external* mail server (a different provider than your website host, or a dedicated transactional service). They authenticate your site to that server. **They simply aren't required when you rely on same-provider native `mail()` as above.** Configure them only if you actually use the SMTP path below.

## How SMTP works (when you do use it)

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
