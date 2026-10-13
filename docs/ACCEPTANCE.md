# Acceptance and readiness

The deliverable is a functional, tested local MVP. It is not declared ready for public production cutover without the external configuration and operator review below. The legacy `../chandi` project and its records were preserved.

## Core acceptance mapping

| # | Criterion | Status / evidence |
|---:|---|---|
| 1 | Laravel starts | Passed; local HTTP requests and browser runs |
| 2 | MariaDB connects | Passed; actual MariaDB version query, native `mariadb` driver |
| 3 | Migrations execute | Passed; all eight migrations, 44 tables |
| 4 | Homepage renders | Passed; six responsive widths |
| 5 | Rate pages load | Passed; country, units, history, ratio, calculator and city routes |
| 6 | Conversion calculations | Passed; exact-decimal conversion/quantity/purity tests |
| 7 | Honest real-data labels | Passed with real Gold API spot and separately timestamped PKR/INR conversions; local dealer prices distinguished |
| 8 | Honest historical charts | Passed with explicitly labelled deterministic fixtures; no invented production history |
| 9 | Calculator | Passed in backend and browser; shareable selections and breakdown |
| 10 | Dealer registration persists | Passed against MariaDB, including a full browser application |
| 11 | Admin dealer approval | Passed; review records, notes, email prerequisite and private evidence |
| 12 | Approved dealer login | Passed in browser workflow |
| 13 | Own-profile editing | Passed; edits reset approval/badge and require review |
| 14 | Dealer product management | Passed; ownership, images, moderation, plan limits |
| 15 | Public approved products | Passed; approved active dealer, moderation and current entitlement scope |
| 16 | Public enquiries | Passed; consent, deduplication and spam controls |
| 17 | Own dealer enquiries | Passed; private follow-up and history |
| 18 | Dealer isolation | Passed; other-dealer product/lead/document actions rejected |
| 19 | Admin dashboard | Passed; actual data and management views with role-scoped access |
| 20 | Subscription permissions | Passed; receipt-backed manual activation, expiry, listing/analytics gates |
| 21 | Scheduled fetching | Passed with demo scheduler/worker and actual Gold API fetches from the installed local minute cron |
| 22 | SEO and sitemap | Passed; SSR metadata, per-page descriptions, structured data, indexing and sitemap scopes |
| 23 | Responsive navigation/layout | Passed at all six requested widths |
| 24 | Physical-silver-only platform | Implemented; no cryptocurrency platform features or legacy integration |
| 25 | Secrets | Private environment/test credential files ignored; API keys/TOTP encrypted at rest; secrets excluded from flashed input and audit changes |
| 26 | No fake production data | Passed; production query/ingestion guards reject simulated snapshots |
| 27 | Automated tests | 85 passed, 392 assertions; 12 Apache browser tests passed, plus the earlier 10-test full workflow run |
| 28 | Deployment README | Delivered with cPanel runbook, rollback, cron and backup guidance |

## Implemented and tested

Public market pages/empty states; decimal calculation/display; deterministic demo separation; provider normalization/reliability logic using HTTP fixtures; real MariaDB persistence; supported Laravel authentication and roles; dealer applications/review/isolation; moderation; enquiries/lead status; manual billing/subscriptions/expiry; alerts and privacy analytics; CMS/metadata/sitemap; responsive layouts. Queue/scheduler processing was executed with local log mail and the demo provider. Local homepage Lighthouse: 99 performance, 100 accessibility, best practices and SEO.

## Implemented, but not fully externally tested

Metals.Dev live/history adapters and encrypted credential configuration; SMTP transport; administrator authenticator enrollment/recovery; actual cPanel/HTTPS/proxy runtime; supported-LTS MariaDB deployment; operational backups/restore. Test-only provider responses and local log mail are not evidence of successful external integrations.

## Requires credentials or operator configuration

- Database/hosting access for a supported production MariaDB release and an appropriate PHP runtime. Local PHP 8.2.4 requires the documented Laravel 12 fallback; Laravel 13 requires PHP 8.3+ and an upgrade test.
- Optional paid Metals.Dev/history provider key and rights; production provider limits, attribution and refresh/freshness policy. The configured public Gold API latest feed needs no key.
- SMTP host/port/scheme/credentials and verified sender; operator contact/failure notification email.
- HTTPS, canonical domain, trusted proxy IPs where required, scheduler, queue worker and backups.
- Final legal operating identity, contact details, retention decisions and review of the seeded legal drafts.
- Search Console verification and any separately consented third-party analytics configuration.

## Not implemented / intentional scope

Automated WhatsApp notification delivery and multilingual translations. The WhatsApp provider contract and click-to-chat links exist. Roles/permissions are defined in reviewable code; the admin UI provides role inspection and user assignment rather than an arbitrary permission editor. CMS is a safe plain-text editor rather than an arbitrary HTML/WYSIWYG builder. Third-party analytics IDs can be stored but no external tracking script is automatically injected.

Online purchase checkout, payment gateways, escrow, custody and delivery collection are intentionally outside the requested MVP. No actual production DNS/hosting cutover was performed. No real dealer identity checks, bank receipt confirmation, external email delivery or licensed market-data connection is claimed by test fixtures.
