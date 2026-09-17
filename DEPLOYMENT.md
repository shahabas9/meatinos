# MeatinOS Production Deployment Checklist

## Runtime

- PHP 8.2 or newer with PDO MySQL, mbstring, OpenSSL, JSON, fileinfo, and cURL.
- MySQL 8.0+ or MariaDB 10.4+ using `utf8mb4`.
- Apache 2.4 or Nginx with the web root restricted to `public/`.
- TLS certificate, HTTP-to-HTTPS redirect, HSTS after HTTPS validation, and secure cookies.
- Set `APP_FORCE_HTTPS=true` only on the TLS-enabled production host; this forces Secure cookies and HSTS.

## Database and secrets

- Create a dedicated database and application user; do not use `root`.
- Grant only `SELECT, INSERT, UPDATE, DELETE` on the MeatinOS schema for the web application. Use a separate migration account for schema changes.
- Store `.env` outside the served document root or retain the included deny rules.
- Preserve the installer-generated `APP_KEY` in secret backups. Changing it makes stored integration credentials unreadable. Never place an OpenAI API key in source files, the ZIP, client-side JavaScript or logs; configure it only in Admin settings after deployment.
- Replace the local administrator password before real data is entered.
- Back up the database daily, encrypt backups, keep off-site copies, and perform quarterly restore tests.
- Apply pending migrations before switching traffic to the new release. Use `php bin/migrate.php` with a separate migration account, or the authenticated `/update.php` cPanel updater when the configured account has temporary schema-change privileges.
- Run `php bin/preflight.php`; deployment is blocked until every check passes.

## Scheduled operations

- Run `php bin/worker.php` every five minutes.
- Run `php bin/healthcheck.php` from infrastructure monitoring.
- Rotate `storage/logs/app.log`, keep `storage/reports` private and writable, and retain security/audit logs and generated reports according to Meatin policy.
- Connect sensor ingestion, GPS, biometric terminals, SMTP, and SMS through authenticated service accounts and TLS.

## Go-live controls

- Create named users and assign the least-privilege role for each department.
- Validate opening inventory lots, storage capacities/thresholds, product shelf lives, customer credit limits, supplier approvals, vehicle calibration, employee pay rates, and chart of accounts.
- Complete user acceptance tests for one live-bird batch through invoice and proof of delivery.
- Obtain management sign-off for finance, payroll, HACCP/quality, and data-retention rules.
- If Meatin AI is enabled, approve the OpenAI account/project, budget, model, permitted users, retention period and human-review policy. Confirm that AI remains advisory and cannot post or approve ERP transactions.
- Review the audit trail, failed-login alerts, backup status, sensor freshness, and open critical alerts on go-live day.
- Remove or disable all seeded demo accounts (`admin`, customer portal, driver, Accounts, and HR); create named production users instead.
- From the source repository, run `php tests/workflow.php` against a staging copy and confirm all QA records are rolled back. The hardened cPanel distribution intentionally excludes development tests and demo seed utilities.

## Release sequence

1. Put the application behind a maintenance page and take a verified database backup.
2. Deploy the release with `public/` as the only web root and install the production `.env` outside public access.
3. Run `php bin/migrate.php` or sign in as a settings administrator and use `/update.php`; both paths share the same database advisory lock and apply only pending versioned migrations. Then run `php bin/preflight.php`. For source/staging releases, also run `php tests/smoke.php` and `php tests/workflow.php` before packaging.
4. Start the five-minute worker schedule and external health monitoring.
5. Enable traffic only after preflight, backup, TLS, integration, and management sign-offs are green.
