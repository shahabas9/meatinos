# MeatinOS Production QC Report

**Build:** MeatinOS cPanel 2.6.0 — Additional Requirements & Workflow Reliability Update  
**QC date:** 11 September 2026  
**Runtime tested:** PHP 8.2.12, MariaDB 10.4.32 via XAMPP, Bootstrap 5.3.3, jQuery 3.7.1, Chart.js 4.4.7

## Release result

The source application, clean cPanel installer and authenticated browser updater passed functional, workflow, schema, security, India-localization, browser and packaging verification. Meatin AI is intentionally disabled and contains no API key; a live paid provider call was not made because the administrator has not configured a key. Production enablement remains conditional on the destination cPanel host passing `php bin/preflight.php` with its real HTTPS URL, dedicated database user, secrets, cron, backup and integration configuration.

## Automated results

- PHP syntax: **94 source/runtime and test PHP files passed, 0 failed**; the full release package contains **81 PHP files** and the update overlay contains **79 PHP files** after development-only exclusions.
- Application/schema smoke suite: **213 passed, 0 failed**.
- Governed lifecycle integration suite: **37 passed, 0 failed**; all generated QA data was rolled back.
- Sales/CRM modification suite: **19 passed, 0 failed**; rate-card pricing, commercial reports, monthly ledgers, automatic report artifacts and all new schema foundations were verified with rollback/cleanup.
- Clean cPanel installer suite: **12 passed, 0 failed** against an isolated disposable database, including strong-password/HTTPS validation, 29-role seeding, multi-role links, Admin/Accounts/HR account creation, no-demo-data verification, migration state, financial accounts, environment generation and second-run blocking.
- AI/India suite: **22 passed, 0 failed**; disabled-by-default delivery, AES-256-GCM secret storage, no view-layer secret exposure, role isolation, admin-save encryption, fixed server-side Responses endpoint, GST, INR, Indian digit grouping and aligned PHP/MySQL Asia/Kolkata defaults were verified without a network API call.
- Complete-requirements suite: **19 passed, 0 failed**; all requirement-specific tables/modules, 64 live report queries, evidence-document scopes, payroll integration, partner capital/dividends, expenses, salary advances and depreciation postings/reversals were verified with rollback.
- Operational-feedback suite: **16 passed, 0 failed**; ten-stage production mapping, stage-specific measurements, permission-scoped bulk rate calculation/export, salesman drilldown, customer complaint entry, and storage-door alert configuration were verified with rollback.
- cPanel updater suite: **11 passed, 0 failed**; pending-state discovery, latest-version reporting, migration-history consistency, idempotent no-op updates, secured web routes, CSRF, mandatory backup confirmation, checksum display and database advisory locking were verified.
- ERP-requirements completion suite: **19 passed, 0 failed**; packing, dedicated wastage reporting, process flow, carcass allocation terminology, partner detail/grouping, rate-card linkage, storage-door alerts and sales receiver attribution were verified.
- Additional-requirements suite: **20 passed, 0 failed**; damaged-bird reason/disposition tracking, daily employee tasks, office-hours inactivity alerts, consent-based salesman GPS sessions, master-data repair, bird-receipt normalization, storage-zone availability and legacy production-stage normalization were verified.
- Combined automated application result: **388 passed, 0 failed** across ten suites.
- Final full and update cPanel ZIP isolation QC: **PASS**; 81 full-package PHP files, 79 update-overlay PHP files, all 13 migrations, the authenticated updater and both checksums were verified, with no `.env`, install lock, development tests, or demo seeder in either archive.
- Schema: **137 tables**, **13 versioned migrations**, **90 configured UI modules**, **29 roles**, **189 fine-grained permissions**, and **19 active financial control accounts**.
- Authenticated rendered regression: purchase-requisition plant/department masters, employee numbering/reporting lines, ten-stage dashboard normalization, production storage-zone choices, Daily Employee Tasks, Salesman Tracking, damaged-bird wastage and narrow-mobile layouts rendered successfully through the local application.
- Health/worker verification: database, schema and log health passed; the idempotent operational worker completed successfully.

## Workflow evidence

- Purchase requisition/PO governance, configurable approval, approved-supplier enforcement, matched GRN acceptance, inventory receipt, GRNI and input GST/AP settlement and payment reversal passed.
- Live-bird veterinary release, governed production transitions, the ten-step executive process view, quantity and temperature controls, mandatory QC evidence, declared output lots, production inventory receipt and balanced yield passed.
- Sales credit checks, open-quality-hold exclusion, FEFO allocation, allocation register and automatic lot-specific picking passed. Insufficient stock keeps the order pending and raises both a production requirement and an operational alert without over-allocation.
- Invoice issue, output GST/AR/revenue, collection, payment reversal, proof of delivery, finished-goods relief and COGS posting passed; the posted general ledger remained balanced.
- Dispatch cannot depart until nine mandatory vehicle, driver, refrigeration, temperature, fuel, document, hygiene and load-condition checks pass. Existing active dispatches are backfilled idempotently during upgrade.
- Duplicate approvals, receipts, allocations, invoice posting, payment clearing and delivery confirmation are blocked. Nested workflows use database row locks, transactions and savepoints.
- Sales order lines can price automatically from the assigned active rate card and current weighted live-bird cost. A whole-bird base price plus default margin calculates the complete product list in bulk, with per-item adjustment and CSV export. HSN/material quotation lines recalculate header totals, while local market comparison and salesman/customer/vendor analytics are exportable and schedulable.
- Delivery confirmation preserves the original load temperature and records a separate delivery-point temperature with its own timestamp. Newly added eligible masters can be deleted only within 24 hours, with CSRF, permission, status, foreign-key and audit controls.
- Operating expenses, partner capital receipts/refunds, dividends with TDS, salary advances and asset depreciation use controlled approve/post/pay/reverse lifecycles, balanced journals, selected bank/cash balances and mandatory reversal reasons. Partner deductions cannot exceed received capital.
- Payroll combines basic pay, overtime/extra duty, configured benefits, sales commission and controlled salary-advance recovery; deductions are capped at gross pay before bank/ledger posting. Active partner ownership and per-partner nominee allocation are each capped at 100%.
- Detailed bird-process measurements cover hanging through chilling/grading/storage, including defeathered count, separate head/defeathering/evisceration wastage, liver/heart/gizzard yields, screw-chiller temperature, grading weight, water level, and configurable storage-door duration alerts; batch-level live-bird, labour, packaging, utilities, quality, logistics, waste and overhead costs feed profitability reporting.
- Damaged birds are recorded separately by production batch, ten-stage process point, bird count, weight, reason, disposition and status; dedicated register, wastage tab, CSV export and scheduled report keep damage analysis separate from finished output.
- Daily employee tasks expose assigned, in-progress, pending and completed work to the employee, direct manager and HR-authorized users. The worker generates configurable inactivity reminders only during office hours.
- Field-sales tracking begins only from an explicit employee action and records customer/prospect, planned route, purpose, location history, duration and travelled distance until the employee completes the visit; one live GPS update runs at a time and failed updates retry on the next interval.
- Upgraded receipts with approved veterinary evidence automatically move out of quarantine, while linked production receipts remain released. Legacy production records that previously skipped visible intermediate stages render those earlier steps as completed before the current stage.
- Governance registers cover partner nominees and printable receipts/welcome letters, employee appointment letters, legal cases, ROC filings, certificates, government loans, office files, corporate/production calendars, vehicle gate movements and E-Way bills.

## Security and isolation evidence

- Argon2id passwords, forced first-login password changes for Accounts/HR, login throttling, CSRF protection, prepared statements, strict sessions, security headers and audited state transitions are active.
- Users may hold multiple roles; explicit user allow/deny overrides are supported. Customer and driver records remain scoped to their linked customer/employee.
- The document vault verifies real MIME type, limits files to 10 MB, uses randomized non-public paths, protects download routes with permissions and records upload/download audit events.
- The cPanel build excludes `.env`, installation locks, runtime logs, demo seed utilities, demo credentials and the development test suite.
- The browser updater is restricted to users with `settings.manage`, validates CSRF, requires explicit backup acknowledgement, shows pending-file SHA-256 fingerprints, records success/failure in the audit trail and shares a per-database MySQL advisory lock with the CLI migrator.
- Location tracking is limited to active sales employees, requires a visible browser-initiated check-in, remains permission-scoped for management views, and is audit logged. v2.6.0 does not invent a new retention period; production rollout requires the organisation's approved HR/privacy retention policy.
- The cPanel build contains no OpenAI key. The installer generates a unique `APP_KEY`; Admin can add, replace, test or remove an OpenAI key from System Settings. The key is encrypted at rest, only its last four characters are surfaced, provider requests use `store=false`, and question/response metadata is permission-scoped and audited.
- Meatin AI cannot generate SQL or invoke mutation functions. It receives bounded, prebuilt, permission-filtered summaries and is instructed to remain advisory; purchase/sales approvals, ledger posting, stock changes, dispatch, payments and payroll remain governed human workflows.

## India localization evidence

- System/company/installer defaults are `India`, `INR` and `Asia/Kolkata`; displayed money uses `₹` and Indian grouping.
- Finance and workflow terminology uses GST, chart controls are Input GST Credit and Output GST Payable, and the configurable GST rate drives order-line calculations.
- Demo fixtures use Indian locations, GSTIN-style tax identifiers, `+91` phones, Indian vehicle registrations and local market benchmarks. HSN, FSSAI, Aadhaar and PAN commercial fields remain available.

## Production release gate

Before public traffic, configure the real HTTPS domain/certificate, a dedicated least-privilege database account, unique named users and secrets, monitored five-minute worker cron, encrypted backups with a restore test, SMTP/SMS and approved sensor/GPS/biometric integrations. Finance, payroll, GST and HACCP/quality owners must complete UAT and sign-off. If AI is enabled, management must approve the OpenAI project, budget, model, authorized roles, retention and human-review policy. Go live only after `php bin/preflight.php` reports every check as `PASS`.
