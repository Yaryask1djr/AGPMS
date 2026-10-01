# AGPMS security remediation — 1 October 2026

This incremental patch applies **after** the uploaded `AGPMS-review.patch`, based on commit `86d64ff`. It does not apply directly to the unchanged GitHub main branch. It contains corrective code and tests; it has not been applied to your Windows installation or pushed to GitHub.

## Changes

- Removes recovery links from browser responses. Stores token hashes, enforces expiry and one-time consumption, increments account auth version, and revokes API credentials on reset.
- Recovery is intentionally unavailable until a verified delivery adapter is configured. `AGPMS_RESET_DELIVERY_ADAPTER` must be an absolute path outside the web root, returning a callable `(int $userId, string $token): bool`. It must resolve a previously verified contact from the account, deliver using a configured provider and trusted HTTPS application URL, and return true only after provider acceptance. It must never use the submitted phone as proof of identity or log tokens. No provider is assumed or silently configured.
- Requires current active/approved account state and auth-version match for session access. Existing sessions lacking an auth version must log in again.
- Protects user exports and CSV fields; uses CSRF on cookie-authenticated mutations and POST forms for approval/revocation/cancellation/scanning. API bearer authentication uses hashes, eight-hour expiry and headers only.
- Uses a shared scan transaction service with pass row locking and durable event IDs. One-time/delivery passes cannot re-enter after checkout. Device timestamps are validated metadata; entry authorization always uses current server/database time. Check-out can be recorded even if the pass was revoked, expired or blacklisted.
- Offline scans are queued for later reconciliation, **not permission to admit a visitor**. Results acknowledge individual event IDs. Permanent denials are recorded and shown; retryable failures and newly queued events stay in storage. The QR callback uses this path. Queue data is scoped to the account ID.
- Service worker caches explicit static files only; authenticated pages and API responses remain network-only. This does not implement a fully offline authenticated application shell. Push delivery is not configured.
- Routes incident POST resolution before generic creation. Treats mail acceptance/failure truthfully; `mail()` acceptance does not prove final delivery. A production delivery provider/outbox remains future work.
- Restricts maintenance scripts to CLI and preserves checked-in/out states during dashboard expiry updates. Cancellation now revokes rather than destroys pass history.

## Apply on a copied project and test database first (CMD)

1. Extract this ZIP to a convenient folder. Make a filesystem copy of your AGPMS project and export a database backup. Keep your current staged work intact.
2. Use an isolated test database and edit **only the copied project's `.env`** to select it. Obtain your actual schema-only export and confirm `users.id` is `INT` and all existing required tables (`users`, `gate_passes`, `visit_logs`, `blacklist`, `notifications`, `admin_activity_log`, `complaints`, `shifts`) exist. This migration does not repair every pre-existing schema omission.
3. From the copied project, replace the example patch path below with the extracted location:

```cmd
git apply --check "C:\path\AGPMS-security-fixes.patch"
git apply "C:\path\AGPMS-security-fixes.patch"
```

If the check fails, stop: the copied project differs from the supplied patch baseline. Do not force, reset, or overwrite files.

4. In phpMyAdmin, select the isolated test database and import `migrations/20261001_security.sql`. It is additive, but it deliberately revokes existing API tokens. It expects the application's existing INT user IDs and InnoDB tables. Do not import `schema_fix.sql` into a database containing real data; that script drops tables.
5. Lint from the copied project:

```cmd
for /R %f in (*.php) do @C:\xampp\php\php.exe -l "%f"
```

6. Run the service integration suite. It creates its own randomly named temporary database and drops only that database. Use a dedicated test DB administrator with CREATE/DROP DATABASE rights. Put the test password in the local environment only; do not paste it into chat:

```cmd
set AGPMS_TEST_HOST=127.0.0.1
set AGPMS_TEST_USER=root
set AGPMS_TEST_PASSWORD=YOUR_LOCAL_TEST_PASSWORD
C:\xampp\php\php.exe tests\security_integration.php
set AGPMS_TEST_PASSWORD=
```

This suite uses a schema-only fixture supplied by the project owner; it does not substitute for testing your real schema and HTTP workflows.

7. If Node is available: `node tests\browser_security.test.cjs`.
8. In a browser, log in again and verify: anonymous user export fails; revoked account fails on next request; mutations without CSRF fail; one-time pass check-in/retry/check-out/re-entry behaves correctly; multiple same-event submissions produce one visit; incident POST resolution updates instead of creates; mail failure shows failure; logout leaves no cached authenticated dashboard; reset response never contains a recovery link; provider recovery works only with the configured verified adapter. Run simultaneous scan requests to verify locking in your MariaDB 10.4 environment.

## Validation completed here

- All 70 PHP files pass PHP 8.3.6 syntax lint. Your target is PHP 8.2.12; repeat lint there.
- Scanner/service-worker JavaScript syntax passes.
- Executed tests against the actual scanner code: retryable and concurrently queued events are preserved; only durable acknowledgements are removed.
- Executed service-worker test: authenticated/export/API/query-string routes are not intercepted.
- Schema + additive migration accepted by MariaDB 10.11.14 bootstrap mode. Your target MariaDB 10.4.32 still needs verification.
- Full PHP/MySQL HTTP and service integration tests were **not executed here**: the environment rejects the sockets needed to start a database server. The integration test is included for XAMPP.

## Remaining limits

This addresses the ten requested findings with conservative behavior, not a certification of production readiness. Provider setup, real-schema validation, concurrent HTTP tests, malformed API payload tests, retention/monitoring, upload re-encoding/private QR storage, broader pagination and the other audit items still need work. Mail uses the server's configured transport; account recovery stays unavailable without a verified provider adapter. Back up before promotion and run integration tests before deployment.
