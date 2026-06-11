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

---

## Database schema changes

The plugin manages its own tables via `dbDelta()` in `includes/class-activator.php`. Schema changes require two steps:

### 1. Edit the table definition

Add or change columns in `WPAPPT_Activator::create_tables()`. `dbDelta` can add new columns and indexes — it cannot remove or rename them.

### 2. Bump `WPAPPT_DB_VERSION`

In `wp-appointments.php`, increment the constant:

```php
define( 'WPAPPT_DB_VERSION', '1.2' ); // was '1.1'
```

### How the upgrade runs

On every page load, `WPAPPT_Activator::maybe_upgrade()` compares `WPAPPT_DB_VERSION` against the value stored in `wpappt_db_version` (a `wp_options` row). When they differ, `dbDelta` runs and updates the stored value. This means:

- **Local:** simply reload any admin page after editing the code — the migration runs automatically.
- **Production:** uploading the new plugin files is enough — the migration runs on the next page load, with no deactivate/reactivate needed.

### What `dbDelta` cannot do

For changes that `dbDelta` cannot handle (renaming a column, changing a column type, dropping a column), add a raw `$wpdb->query()` call inside `maybe_upgrade()` before the `create_tables()` call, guarded by a version check:

```php
public static function maybe_upgrade(): void {
    $stored = (string) get_option( 'wpappt_db_version' );
    if ( $stored === WPAPPT_DB_VERSION ) {
        return;
    }

    // Example: rename old_col → new_col, added in 1.2
    if ( version_compare( $stored, '1.2', '<' ) ) {
        $wpdb->query( "ALTER TABLE {$wpdb->prefix}appointments_example RENAME COLUMN old_col TO new_col" );
    }

    self::create_tables();
    update_option( 'wpappt_db_version', WPAPPT_DB_VERSION );
}
