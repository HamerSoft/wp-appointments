# SMTP Configuration

The plugin sends transactional emails (booking confirmations, cancellations, follow-ups) via WordPress's `wp_mail`. By default WordPress uses the server's PHP `mail()` function, which is often blocked or lands in spam.

## Recommended: transactional email service

Do **not** use your personal `@live.nl` mailbox for sending. If those credentials leak, your entire personal account is at risk.

Instead, use a dedicated transactional email service. They issue an API key scoped only to sending mail — if it ever leaks, you revoke it and generate a new one without touching your personal account.

**[Brevo](https://brevo.com)** is the recommended choice:
- Free tier: 300 emails/day (more than enough for a solo practice)
- No credit card required
- Provides SMTP credentials that work with this plugin out of the box

After signing up, find your SMTP credentials under **Brevo → Transactional → Settings → SMTP & API**.

| Field      | Value                        |
|------------|------------------------------|
| SMTP Host  | `smtp-relay.brevo.com`       |
| SMTP Port  | `587`                        |
| Encryption | `TLS`                        |
| Username   | your Brevo account email     |
| Password   | your Brevo **SMTP key** (not your Brevo login password) |

---

## Credential storage — resolution order

The plugin resolves each setting in this order, using the first value it finds:

1. **Environment variable** — set at the server/hosting level, never written to disk
2. **`wp-config.php` constant** — in the file but not in the database
3. **Settings page** (**Appointments → Settings → Outgoing Email**) — stored in the database

Use the highest level your hosting provider supports.

---

## Option A: environment variables (most secure)

Set these on your server (via your hosting control panel, `.env` file, or web server config). The values never appear in any file you manage.

```
WPAPPT_SMTP_HOST=smtp-relay.brevo.com
WPAPPT_SMTP_PORT=587
WPAPPT_SMTP_ENCRYPTION=tls
WPAPPT_SMTP_USERNAME=you@example.com
WPAPPT_SMTP_PASSWORD=your-brevo-smtp-key
```

No changes to `wp-config.php` needed — the plugin reads env vars automatically.

---

## Option B: wp-config.php constants

Add these above the `/* That's all, stop editing! */` line. The password is in a file on the server but never in the database.

```php
define( 'WPAPPT_SMTP_HOST',       'smtp-relay.brevo.com' );
define( 'WPAPPT_SMTP_PORT',       587 );
define( 'WPAPPT_SMTP_ENCRYPTION', 'tls' );
define( 'WPAPPT_SMTP_USERNAME',   'you@example.com' );
define( 'WPAPPT_SMTP_PASSWORD',   'your-brevo-smtp-key' );
```

If your host supports env vars, prefer Option A and reference them here instead of hardcoding:

```php
define( 'WPAPPT_SMTP_PASSWORD', getenv( 'WPAPPT_SMTP_PASSWORD' ) );
```

---

## Option C: settings page

Fill in **Appointments → Settings → Outgoing Email (SMTP)**. Simplest to set up, but credentials are stored in the WordPress database. Acceptable for low-risk setups where database access is tightly controlled.

---

## Custom sender domain (recommended for production)

By default, Brevo sends emails from a shared Brevo domain. Setting up your own sender domain means emails arrive as e.g. `noreply@yourdomain.com` instead, which looks more professional and significantly improves deliverability (less likely to land in spam).

### Steps in Brevo

1. Go to **Brevo → Senders & IPs → Domains**
2. Click **Add a domain** and enter your domain (e.g. `yourdomain.com`)
3. Brevo will give you a set of DNS records to add — typically two `TXT` records:
   - **SPF** — tells mail servers Brevo is allowed to send on your behalf
   - **DKIM** — cryptographically signs outgoing mail so receivers can verify it hasn't been tampered with
4. Add those records via your domain registrar's DNS settings
5. Click **Verify** in Brevo once the records have propagated (can take up to 24 hours)

### Update the sender address in the plugin

Once your domain is verified, update the **Sender Name** and **Notification Email** fields under **Appointments → Settings** to use your custom domain address, e.g. `noreply@yourdomain.com`. This address must match a verified sender in Brevo.

### Why this matters

| Without custom domain | With custom domain |
|---|---|
| `From: Your Name via brevo.com` | `From: Your Name <noreply@yourdomain.com>` |
| Higher spam risk | Better deliverability |
| Looks third-party | Looks like your own site |

---

## Local development

In the Docker dev environment, all outgoing mail is intercepted by **MailHog** regardless of SMTP settings. No real emails are sent.

- View captured emails at **http://localhost:8025**
- MailHog is configured automatically by `bin/setup-wp.sh`
