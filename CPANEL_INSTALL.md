# MeatinOS cPanel Installation

## Before uploading

In cPanel, create:

1. A subdomain or addon domain such as `erp.example.com` with SSL enabled.
2. A new empty MySQL database.
3. A new MySQL user with a long random password.
4. Assign that user **ALL PRIVILEGES** on only the new MeatinOS database. The application uses normal data privileges; the installer needs schema-creation privileges during setup.

## Upload and extract

1. Upload the supplied versioned `MeatinOS-cPanel-v2.6.0.zip` using cPanel **File Manager**.
2. Extract it into a private application directory, for example `/home/CPANEL_USER/meatinos`.
3. Set the domain document root to `/home/CPANEL_USER/meatinos/public`. Do not point the domain at the project root.
4. Confirm PHP 8.2 or newer and enable `pdo_mysql`, `mbstring`, `openssl`, `json`, `fileinfo`, and `curl` in **MultiPHP Manager / Select PHP Version**.
5. Ensure the application directory and `storage/` are writable by PHP during installation. Normal cPanel permissions are usually directories `0755` and files `0644`.

## Run the one-time installer

Open `https://erp.example.com/install.php` and enter:

- The final HTTPS application URL.
- The empty cPanel database name, user, and password. cPanel normally prefixes names with the account username.
- Unique Admin, Finance/Accounts, and HR email addresses and strong passwords.

The installer creates the complete 137-table schema, applies all 13 bundled migrations, seeds 29 operational roles and 189 fine-grained permissions, establishes INR/India/GST financial and production settings, and creates the three named users. It generates a unique `APP_KEY` for application-level secret encryption and creates no demo transactions. After verification it writes `.env`, creates `storage/installed.lock`, and permanently disables itself.

The production ZIP intentionally contains no demo passwords, demo-data seed commands, or development test suite.

## After installation

1. Sign in at `https://erp.example.com/?route=login`.
2. Finance and HR must change their temporary passwords immediately.
3. Remove write permission from the project directory if you temporarily increased it; keep `storage/logs`, `storage/uploads`, and `storage/reports` writable. Keep the project root outside the public web root so controlled documents and generated reports cannot be requested directly.
4. Configure a cPanel cron job every five minutes:

   ```text
   */5 * * * * /usr/local/bin/php /home/CPANEL_USER/meatinos/bin/worker.php >/dev/null 2>&1
   ```

5. Configure uptime monitoring to run `bin/healthcheck.php`, daily encrypted database backups, SMTP/SMS delivery, and the sensor/GPS/biometric integrations.
6. If Terminal is available, run `php bin/preflight.php`. The release gate must pass before real production traffic and data are enabled.

## Optional Meatin AI setup

MeatinOS contains no OpenAI API key and AI is disabled by default. After installation, sign in as Admin, open **System Settings → OpenAI for Meatin AI**, paste a project API key, choose the approved model, save, and use **Test connection**. The key is encrypted with the installer-generated `APP_KEY`; only its last four characters are shown later. Removing the key immediately disables AI. Complete management approval and data-retention review before enabling it.

## Upgrades

1. Put the site in a maintenance window and make verified backups of both the database and application files.
2. Upload and extract the new release over the application directory. Preserve the production `.env`, `storage/installed.lock`, uploaded documents, reports and logs.
3. Sign in with an account that has **System Settings: Manage**, then open `https://erp.example.com/update.php`.
4. Review the current/bundled migration versions, pending filenames, SHA-256 fingerprints and server checks.
5. Confirm the backup and choose **Apply migrations**. The updater runs only migrations not already recorded in `schema_migrations`, in filename order, under a database advisory lock that prevents simultaneous web or terminal runs. Every successful or failed attempt is audited.
6. Refresh the application, verify the upgraded functions and run `php bin/preflight.php` from cPanel Terminal when available.

The updater uses the database account in `.env`, so that account needs schema-change privileges during the maintenance window. Hardened installations that use a separate migration account should continue to run `php bin/migrate.php` from cPanel Terminal instead. Never run `install.php` against an existing database. MySQL DDL can auto-commit; if a migration fails, stop and restore the verified backup before retrying.
