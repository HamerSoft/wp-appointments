# Local Development

## Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running
- `Divi.zip` placed in the project root (download from your Elegant Themes account)

---

## First-time setup

```bash
docker compose up -d
bash bin/setup-wp.sh
```

`setup-wp.sh` waits for the database, installs WordPress, enables pretty permalinks, installs Divi, activates the plugin, and configures MailHog for email.

---

## Daily use

### Start the server

```bash
docker compose up -d
```

### Stop the server

```bash
docker compose stop
```

This stops the containers but keeps the database and WordPress files intact. Everything resumes exactly where you left off next time you start.

---

## URLs

| Service   | URL                              | Credentials      |
|-----------|----------------------------------|------------------|
| Site      | http://localhost:8080            | —                |
| WP Admin  | http://localhost:8080/wp-admin   | admin / admin    |
| MailHog   | http://localhost:8025            | —                |

---

## Start over from scratch

To wipe everything (database, uploads, all WordPress data) and start fresh:

```bash
docker compose down -v
docker compose up -d
bash bin/setup-wp.sh
```

The `-v` flag removes the Docker volumes. After this, `setup-wp.sh` must be run again to reinstall WordPress.

---

## Email

All outgoing mail is intercepted by MailHog in the local environment — no real emails are ever sent. Open **http://localhost:8025** to inspect them.

See `docs/smtp-configuration.md` for production email setup.
