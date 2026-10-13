# Market dashboard update — 13 October 2026

- PHPUnit: **90 passed, 449 assertions** against the dedicated MariaDB test database. New checks cover market-only route blocking, dated FX history conversion, idempotency, archive fallback/gaps, missing INR, invalid inputs, PSL raw-unit handling, fetch cooldown and last-good retention.
- Playwright: **13 passed** against real Apache; responsive layouts at 360/390/768/1024/1440/1920, country/weight/period switching, one-year histories, PSL unit restrictions, automatic display refresh, mobile navigation, calculator and Apache source-file protection.
- Real archive import: 364 daily records for 2025-10-13 through 2026-10-12. 2025-12-10 returned 404 and remains missing. All three currency histories use dated FX.
- GoldToday PSL returned a timestamped quote. Its unit conflict is visible; historical PSL is not available upstream and is not fabricated.
- Screenshots inspected: `tests/browser/artifacts/home-1440.png`, `home-390.png`, `live-rates-1440.png`.
- Live cron log confirms both spot refresh and reference checks. Existing main and legacy databases were not reset.

The earlier implementation evidence below refers to the retained, now-paused marketplace phase.

# Verification — 13 October 2026 (Asia/Karachi)

## Final results

| Check | Actual result |
|---|---|
| PHPUnit | **85 passed, 392 assertions**; no failures, risky tests or skips in the final run |
| Playwright / installed Chrome | **10 passed** in the initial full workflow run; **11 passed** in the Apache/subdirectory run; **12 passed** after the real three-country market integration |
| Responsive checks | 360, 390, 768, 1024, 1440, 1920 px; public and authenticated admin/dealer pages; no document-level horizontal overflow or JS exceptions on tested pages |
| Vite production build | Passed; standalone Alpine entry ~60 KB, chart engine ~150 KB loaded only for available chart data; Livewire loaded only on the calculator |
| Composer validation / security audit | Valid manifest; no reported vulnerability advisories |
| npm audit | No reported vulnerabilities |
| MariaDB | Actual version 10.4.28-MariaDB, native Laravel `mariadb` configuration; 44 application tables |
| Application migrations | All eight migration files executed successfully |
| Scheduler + database worker | Manual scheduler run queued a demo refresh; refresh and notification jobs completed; no failed jobs reported |
| Health | HTTP 200, database `mariadb`, market `recent` with real Gold API observations in the main application |
| CSRF | POST `/enquiries` without a token returned HTTP 419 |
| Private source protection | Apache requests for `.env`, `.env.testing`, `.git/config`, Composer files, application source, logs, direct `public/index.php` and scripts returned HTTP 403; private image paths returned 404 |

Earlier implementation defects were resolved before the final passing runs. No coverage percentage is claimed. PHP tests and authenticated browser fixtures use the dedicated `chandi_live_test` MariaDB database and refuse other database names/drivers. Public Apache checks read the main application and record ordinary test-visitor activity. Browser caches/rate limits are isolated from the main application and reset by the guarded test fixture script.

The Apache follow-up verified **http://localhost/chandilive/** directly against the existing XAMPP service. Nine layout, navigation, calculator and chart checks passed at all six widths, plus two Apache checks for private-file denial, CSRF enforcement, asset loading and branded favicon files. The actual ICO contains four image sizes and is served as `image/x-icon`. The 79 PHP tests (348 assertions) also passed after the subdirectory link changes. Apache configuration outside this project and the legacy application were not changed.

Re-run the local Apache checks with XAMPP running:

```sh
CHANDI_BROWSER_URL=http://localhost/chandilive CHANDI_APACHE_URL=http://localhost/chandilive npx playwright test tests/browser/layout.spec.mjs tests/browser/apache.spec.mjs tests/browser/live-rates.spec.mjs
```

## Browser evidence

Public QA initially checked unavailable rates; the latest run checks real Gold API observations and three currency benchmarks in the main local application, while the dealer directory remains empty. Authenticated workflow QA uses the isolated test database and an explicit development-demo banner. Test dealer, product, enquiry, editorial content and payment receipt are fictional test fixtures; they are not real businesses, collected revenue or live market data.

The full browser workflow exercised account sign-in, dealer application submission with a private upload, administrative approval, dealer product creation, moderation, public product viewing, consented enquiry submission, private dealer follow-up, invoice creation, manual payment confirmation, Premium activation, and CMS publication with escaped script text. It also checked the actual populated demo chart.

Evidence generated locally:

- [Desktop homepage](../tests/browser/artifacts/home-1440.png)
- [Mobile homepage](../tests/browser/artifacts/home-390.png)
- [Calculator result](../tests/browser/artifacts/calculator.png)
- [Desktop administrator console — test data](../tests/browser/artifacts/admin-1440.png)
- [Mobile administrator console — test data](../tests/browser/artifacts/admin-390.png)
- [Desktop dealer workspace — test data](../tests/browser/artifacts/dealer-1440.png)
- [Mobile dealer workspace — test data](../tests/browser/artifacts/dealer-390.png)
- [Explicitly labelled simulated history](../tests/browser/artifacts/demo-history.png)
- [Playwright machine-readable results](../tests/browser/artifacts/results.json)

Artifacts are ignored in Git but present in this delivered workspace. Rerunning QA regenerates them. Temporary preview processes are stopped after delivery checks; restart using the README commands.

## Real three-country feed follow-up

The real public silver endpoint and daily FX endpoint both responded successfully. Central fetching stored non-demo USD spot with separate PKR/INR FX snapshots and timestamps. The installed local minute cron entry fetched a second observation and processed its alert-evaluation job with zero provider failures. Twelve Apache browser checks passed, including all six responsive widths, three currency cards, source attribution and a simulated 60-second display refresh. Eighty-five PHP tests (392 assertions) passed, including exact INR conversion, FX cache reuse, invalid/future timestamps, missing INR, failure retention, stale-FX labels and suppression of PKR alerts. [Live market screenshot](../tests/browser/artifacts/live-rates-1440.png).

## Local Lighthouse audit

The final mobile-emulation audit of the main homepage scored:

| Category | Score |
|---|---:|
| Performance | **99** |
| Accessibility | **100** |
| Best practices | **100** |
| SEO | **100** |

FCP 1.6 s; LCP 1.8 s; total blocking time 10 ms; cumulative layout shift 0 in this run. The initial performance score of 66 led to responsive hero image variants, removal of unnecessary global Livewire/Chart.js loading, a smaller chart engine chunk and Apache compression/cache configuration. The functional browser checks passed after these changes.

[Full Lighthouse report](../tests/browser/artifacts/lighthouse.report.html), [JSON data](../tests/browser/artifacts/lighthouse.report.json).

These are local measurements from one homepage audit, not a production hosting benchmark or a WCAG certification. Real provider data and hosting performance must be measured after deployment configuration.

## Tested backend scope

- Exact gram/tola/troy ounce/kg conversions, FX inversion, fineness, quantity, premiums/discounts, per-item charges and fees; very large decimal display precision.
- Provider HTTP fixtures, invalid responses, absent units/timestamps, failures, retry limits, quotas, duplicate detection, snapshot aggregation, stale states and production rejection of demo observations.
- Authentication, registration privilege restrictions, email verification, reset/update/confirmation flows, deactivation, administrator permissions, TOTP challenge/replay controls and reviewer document restrictions.
- Dealer applications, approval and badge prerequisites, re-review, suspension, private document ownership, product owner isolation, image replacement rollback, listing limits and immediate expiry enforcement.
- Public filters and exact normalized weights, enquiry consent/duplication/spam limits, dealer lead isolation, correctly encoded WhatsApp links and separate telephone-click activity.
- Manual invoices, exact payment matching, duplicate receipt prevention, subscription activation/expiry, gated advanced activity, CMS authorization/escaping, sitemap and health.
- Alert opt-in/ownership/unsubscribe, fresh real-data triggers, duplicate reservations, stale queued-delivery cancellation and logged-versus-sent mail status.
- Public session activity with cookie persistence, daily deduplication and DNT suppression.

## Unexecuted external checks

- A real licensed Metals.Dev account response, quota reconciliation and commercial redistribution agreement.
- External SMTP delivery, sender authentication, mailbox receipt/bounces and real authenticator enrollment/recovery on an operator’s device.
- Supported-LTS MariaDB/cPanel runtime, HTTPS/reverse proxy operation, cron behavior on the host, backup/restore drill and load testing.
- Safari/Firefox, a screen-reader audit and complete manual WCAG 2.2 AA verification.
- Production Lighthouse/SEO crawl under genuine content and market-data traffic.

These require external credentials, hosting/operator access or separate manual QA. They are not reported as passed.
