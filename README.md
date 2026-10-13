# CHANDI.LIVE

Silver markets, in focus.

A locally implemented Laravel silver market dashboard for the USA, Pakistan and India. Vendor registration and the marketplace are paused for the initial launch. The separate legacy project in `../chandi` is preserved. The market dashboard is deployed independently; its repository excludes environment credentials and runtime data.

## Appearance

New visitors receive the light theme regardless of their operating-system preference. The header moon/sun button switches between light and dark, remembers the choice in browser local storage, and updates the chart palette without changing the selected market or period.

## Delivery and runtime

Project: `/Applications/XAMPP/xamppfiles/htdocs/chandilive`.

Live application: **https://chandi.live/**. Source: **https://github.com/ceosarzone/chandi_live**.

Local application: **http://localhost/chandilive/** (existing XAMPP Apache).

Laravel 12.69.3 is used because the installed XAMPP PHP is 8.2.4. Laravel 13 requires PHP 8.3+ ([official release requirements](https://github.com/laravel/docs/blob/13.x/releases.md)). No PHP requirement was bypassed. Plan a Laravel 13 upgrade after the hosting runtime is upgraded and the test suite passes on it.

Actual local database: MariaDB 10.4.28, via Laravel's native `mariadb` driver and `pdo_mysql`. There is no SQLite application/test database. This existing XAMPP server was preserved to avoid affecting neighboring projects. Production should use a supported MariaDB LTS release (10.11 or later). Development and tests use separately named databases `chandi_live` and `chandi_live_test`, with generated credentials stored only in ignored environment files.

**Readiness verdict:** market-only dashboard deployed and verified over production HTTPS. See [production operations](docs/PRODUCTION.md) for the Git deployment, runtime, scheduled refresh and rollback procedure. External SMTP and the paused dealer workflows are not enabled for launch.

## Current launch scope — 13 October 2026

The public site is a market dashboard: four quote cards, USD/PKR/INR/PSL chart selection, gram/10 gram/tola/troy ounce/kilogram controls, six periods, observed low/high/change, data tables and source attribution. The favicon remains installed. Registration, dealer profiles, products and enquiries return 404 while `MARKETPLACE_ENABLED=false` (the default). Retained marketplace code and records can be enabled later; the legacy feature suite explicitly enables it. The public navigation and sitemap focus on market pages.

Daily history was imported for **13 October 2025–12 October 2026: 364 records**, each including USD silver and same-date PKR/INR exchange rates. **10 December 2025 is absent upstream** (primary and fallback); it remains a visible gap. Currency API's CC0 daily reference snapshots are separate from Gold API's recorded spot observations, not exchange OHLC/closing prices. Daily records never replace the live feed or trigger alerts. Pakistan and India remain converted benchmarks, not national retail quotations.

```sh
php artisan market:backfill 2025-10-13 2026-10-12
php artisan market:references
```

Backfill is idempotent, validates dates and positive rates, accepts at most 366 completed days, uses bounded eight-request batches and the official fallback. A nonzero command result reports missing dates without inventing prices. The local minute cron now also calls `market:references`: PSL is checked at most every 15 minutes; the previous seven archive dates are checked daily to catch delayed publications.

PSL is fetched from GoldToday's public `api/prices/get?type=pslSilver` endpoint. **Its webpage labels PSL “10 Tola” while the returned price appears inconsistent with that unit.** We preserve the raw PKR quote and source timestamp, visibly label the unit unconfirmed, and disable PSL unit conversion. Do not silently multiply/divide it by ten or imply a verified refinery quotation. GoldToday rejects PSL history requests (only regular gold/silver history are supported), so PSL history accumulates from launch. Fetch failures retain the last observation; quotes older than 24 hours are labelled stale.

Sources: [Gold API](https://gold-api.com/docs), [ExchangeRate-API](https://www.exchangerate-api.com/docs/free), [Currency API](https://github.com/fawazahmed0/exchange-api), [GoldToday](https://www.goldtoday.io/). The previous marketplace implementation described below is retained for a later phase.

## Implemented modules

- Public homepage, silver unit-rate pages, history, gold/silver ratio, calculator, approved city pages, dealer and product directories, profiles, enquiries, articles, guides and CMS pages.
- Exact decimal calculations using Brick Math and BCMath, with scientific tola and troy-ounce constants, purity adjustment, premiums/discounts, charges, fees and quantities. Livewire calculator validates on submission, copies results and keeps shareable selections in the URL.
- Provider adapter interface, Metals.Dev latest and history adapters, inverse FX normalization, central database quotas, file locking, bounded retries, validation, last-known observations, source timestamps, failures and job logs. Daily history stays distinct from timestamped observations; it does not create intraday points.
- Genuine stored historical observations, daily recorded-observation aggregations and bounded chart payloads. Longer chart windows use the final stored observation per UTC date; 24-hour views use timestamped observations only. Changes disclose actual comparison timestamps through the API.
- Dealer applications, private verification documents, recorded review decisions, information requests, approval, re-review, rejection and suspension. Approval requires verified account email. A badge additionally requires a supporting document and a recorded reviewer decision.
- Dealer profile/photos, image re-encoding and resizing, product galleries, moderation, ownership policies, quotations, private enquiry status history and persisted activity.
- Free/Premium/Featured plans configurable in the database/admin UI, monthly invoices, receipt references, auditable offline payment confirmation, renewals, cancellation, expiry and sponsored placements. Public listing visibility respects the current plan immediately at expiry; excess records are retained. Advanced activity access follows active entitlements.
- Verified-user email price watches, expiry, frequency controls, atomic per-observation notification reservation, distinct reservation/sent timestamps, queued mail retries and unsubscribe. Stale, daily historical and simulated observations do not trigger alerts. A WhatsApp provider contract exists; automated WhatsApp delivery is not configured.
- Protected admin console, paginated/filterable/sortable management tables, CMS, local market references, plans/invoices/payments, users, encrypted provider credential storage, provider logs, audit trails, actual analytics and platform settings.
- Laravel authentication, email verification, password reset, throttling, policies/gates, optional administrator TOTP, anti-replay checks, private document authorization and safe account deactivation.
- Server-rendered metadata, canonical pagination, sitemap, robots, Organization/Article/LocalBusiness JSON-LD without manufactured ratings. Empty city pages and filtered directories are not indexed. Unpublished/suspended/over-limit listings are excluded from public results and the sitemap.

Operational content is plain text with preserved paragraphs and escaped markup. Published CMS pages are available at `/{slug}`. Seeded legal text is an operational draft requiring review for the actual business before launch. Guides/FAQs can be maintained as published CMS pages, or guide articles. Roles are defined in the reference seeder; the super administrator assigns users to them. This keeps permission changes in reviewable code.

## Installation

Requirements: PHP 8.2+ for this Laravel 12 build; standard Laravel extensions plus BCMath, PDO MySQL, GD (JPEG/PNG) and Fileinfo; Composer 2; MariaDB; Node 22+ for building assets only. Redis, containers and permanent Node processes are unnecessary.

1. Create a new MariaDB database and an application-specific user with privileges restricted to it. Use a separate database/user for tests. Do not reuse the legacy database.
2. Install dependencies, create `.env`, configure the database and generate the key:

```sh
composer install
cp .env.example .env
# Edit .env securely: DB_* and APP_URL. Do not paste secrets into shell history.
php artisan key:generate
php artisan migrate --seed
npm ci
npm run build
php artisan storage:link
php artisan serve --host=127.0.0.1 --port=8091
```

On this Mac, PHP is `/Applications/XAMPP/xamppfiles/bin/php`. Composer was installed for this task as `/tmp/chandi-composer.phar` (temporary; install Composer permanently for ongoing use). For example:

```sh
/Applications/XAMPP/xamppfiles/bin/php artisan serve --host=127.0.0.1 --port=8091
/Applications/XAMPP/xamppfiles/bin/php /tmp/chandi-composer.phar install
```

The current `.env` is configured for the dedicated local MariaDB database and **http://localhost/chandilive/**. With XAMPP Apache running, this URL works without an Artisan preview server. The repository-root `.htaccess` routes application requests through `index.php`, exposes only specified public assets, and blocks source files, dotfiles and private storage. Links, chart requests and Livewire calculator requests retain the application subdirectory. API secrets, TOTP seeds and verification documents are never in public storage.

On this Mac, the existing Apache worker runs as `daemon`. A read ACL on `.env` and inherited write ACLs on `storage/` and `bootstrap/cache/` allow that worker to run the app while `.env` retains mode 0600. New local clones need equivalent permissions for their actual web-server account. For **production**, configure the domain document root to **public/** as described in the deployment guide.

The browser identity includes an SVG favicon, a four-size ICO (16/32/48/64 pixels), PNG fallbacks and an Apple touch icon, generated from the original silver mark by `scripts/brand-assets.php`.

## Administrator and dealer access

Create the first administrator from trusted server access, after migrations/seeding:

```sh
php artisan chandi:admin your-real-admin@example.com --name="Platform Administrator"
```

The command prompts for a password of at least 12 characters. It does not accept a password argument, upgrade an existing public account, or create a public registration path for administrators. Visit `/login`, then `/admin`. Configure TOTP under `/account/two-factor`; the secret is encrypted at rest. Account recovery with trusted server access: `php artisan chandi:totp-reset EMAIL`, after independent ownership checks.

Normal dealer onboarding: register at `/register`, verify email, complete `/dealer/profile`, and submit. Review at `/admin/dealers/{id}`. An approved dealer can manage products from `/dealer`. Revisions go through moderation. Test/demo accounts do not represent real verification.

For an isolated local demonstration only, set `APP_ENV=local`, `MARKET_DEMO=true`, `MARKET_PROVIDER=demo` in your private `.env`, then:

```sh
php artisan config:clear
php artisan chandi:demo --email=dealer@example.test
```

This prints a newly generated one-time password for the fictional dealer. It creates no administrator and grants no badge. The fixture observations have fixed dates/values and are labelled development demo. Do not enable this against a live dealer database. Production rejects demo ingestion and excludes existing simulated snapshots even if the setting is accidentally enabled. Reset demo records only in a disposable database; never run `migrate:fresh` on real data.

## Market provider configuration

The local application now uses **Gold API** for real XAG/USD spot and **ExchangeRate-API** for daily USD/PKR and USD/INR conversion. The homepage and `/live-silver-rates` show USA, Pakistan and India cards, plus gram/10-gram/tola/troy-ounce/kilogram values. Pakistan and India values are converted spot benchmarks, not local dealer, bullion-association or MCX quotations. Separate silver and FX timestamps, unavailable states and stale labels remain visible.

```dotenv
MARKET_PROVIDER=gold_api
MARKET_DEMO=false
MARKET_STALE_MINUTES=5
MARKET_FX_STALE_HOURS=48
```

Enable the `gold_api` provider record (done locally), with a one-minute refresh and an application quota of 100,000 fetch attempts/month. [Gold API's public endpoint](https://gold-api.com/docs) needs no key and [its terms permit commercial website use](https://gold-api.com/terms). [ExchangeRate-API's open endpoint](https://www.exchangerate-api.com/docs/free) provides daily currency rates; the adapter caches its response for one hour and displays the required attribution. Its raw response is kept private, and the application does not offer a raw FX API. A fetch attempt may use both source endpoints. New observations build local history; Gold API historical backfill requires a separate account/plan. Gold observations do not include a matching gold quote, so the gold/silver ratio remains unavailable with this adapter.

The current user's local crontab has a project-specific minute entry for `scripts/market-refresh.sh`. This fetches market data and processes queued jobs with the existing XAMPP PHP binary, independently of visitor traffic. The Mac must be awake and MariaDB running. Logs: `storage/logs/market-cron.log`. Page displays poll the application's cached `/market/rates` endpoint every 60 seconds, without directly calling the providers. For hosting, configure the production scheduler/worker rather than copying this Mac-specific cron entry; see the deployment guide.

**Optional Metals.Dev adapter:**

Adapter endpoints and units were implemented against [Metals.Dev official API documentation](https://metals.dev/docs). With `currency=USD` and `unit=toz`, metal prices are USD/troy ounce and `currencies.PKR` is the USD value of one PKR; USD/PKR is its decimal inverse.

No provider key was supplied. Commercial redistribution rights have **not** been confirmed for this account. The live adapter is deliberately gated:

```dotenv
MARKET_PROVIDER=metals_dev
MARKET_DEMO=false
METALS_DEV_API_KEY=
MARKET_LICENSE_CONFIRMED=false
MARKET_ADMIN_EMAIL=
```

Set the key securely, confirm the provider agreement for public redistribution, and set `MARKET_LICENSE_CONFIRMED=true`. Alternatively, an authorized market operator can save an encrypted key and rights confirmation in `/admin/providers/{id}`; environment keys take precedence. Enable the Metals.Dev provider record and configure its request quota/refresh interval for the subscribed provider plan. Local request counts are conservative attempted-call reservations per UTC calendar month, not a claim about external provider billing. Usage outside this application consumes the provider's allowance too.

Run `php artisan market:fetch`. Check protected provider requests/errors and snapshots; CLI completion alone does not claim a successful provider fetch. History: `php artisan market:history YYYY-MM-DD YYYY-MM-DD`, at most 30 completed UTC dates per request. PKR must be present in the response; an absent FX series is rejected rather than invented. Historical daily timestamps are a date-storage convention, displayed as daily UTC dates, not exact intraday observations.

## Email, scheduler and queue

Local mail defaults to the Laravel log transport. This records mail for inspection; it does not deliver externally. Use `/verify-email` to request another verification message and inspect the local private log for its signed link. Do not publish the log. For actual delivery, configure `MAIL_MAILER=smtp`, scheme/host/port/username/password, a verified sender, and SPF/DKIM as appropriate for your mail provider. Market failure notifications use `MARKET_ADMIN_EMAIL`.

```sh
php artisan schedule:list
php artisan schedule:run
php artisan queue:work database --queue=default --tries=3 --timeout=90
```

On cPanel, bounded cron workers and the every-minute scheduler are documented in [DEPLOYMENT.md](docs/DEPLOYMENT.md). The scheduler queues centralized fetching, expiry and retention. Visitors never fetch the provider directly. Alert emails contain an explicit unsubscribe action. Notification reservations and sent status prevent normal duplicate evaluation; SMTP transport cannot provide an absolute exactly-once delivery guarantee after an ambiguous network failure.

## Tests and evidence

See [QA.md](docs/QA.md) for actual results and screenshot paths. Tests refuse any database other than `chandi_live_test` on the MariaDB driver. PHPUnit uses transactions and test-specific factories; fixtures never target the application database.

```sh
cp .env.testing.example .env.testing
# Configure a disposable MariaDB test DB/user and APP_KEY in .env.testing.
php artisan config:clear
php artisan test
npm run build
npm audit
```

Browser tests use Playwright and Chromium. The current config uses installed macOS Chrome; set `CHANDI_CHROME_PATH` or leave it empty to use Playwright Chromium on another host. Public tests: `npx playwright test tests/browser/layout.spec.mjs` with the main application running at port 8091.

Full workflow tests require `.env.browser` (copy testing settings, set `APP_ENV=browser`, `DB_DATABASE=chandi_live_test`, `SESSION_DRIVER=database`, `CACHE_STORE=file`, `QUEUE_CONNECTION=database`, `MARKET_DEMO=true`, `MARKET_PROVIDER=demo`, `APP_URL=http://127.0.0.1:8092`). Then:

```sh
php scripts/browser-fixtures.php
php artisan serve --host=127.0.0.1 --port=8092 --env=browser
# In another shell, while the main port 8091 server is also available:
npx playwright test
```

The fixture script explicitly checks the driver, database name and browser environment before refreshing **only** the test database. It stores generated test credentials in `/tmp/chandi-browser-credentials.json` with mode 0600. Delete this temporary file after QA. Do not run PHPUnit concurrently with browser workflows; both use the disposable test database. Screenshots contain clearly labelled simulated or test data, not real dealers or collected revenue.

## Scope and external blockers

Paid Metals.Dev delivery, SMTP delivery, supported-LTS production MariaDB testing, HTTPS/cPanel operation, production backups/restore and production performance benchmarks require the deployment environment. The legal operator/contact identity and legal documents need final review. Automated WhatsApp notification delivery, multilingual translations and any payment gateway are not implemented. Click-to-chat links work. Third-party analytics IDs can be stored; tracking scripts are not automatically loaded. First-party analytics records actual requests and daily session activity, respects DNT and prunes after 90 days. Session counts include local QA requests and are not a claim about organic visitors. Platform roles/permissions are maintained in code, not through an arbitrary permission editor.

No checkout, escrow, custody or gateway integration is included; transactions are arranged directly with dealers. No investment guarantees, fictional reviews or market forecasts are seeded.

Architecture/schema details: [ARCHITECTURE.md](docs/ARCHITECTURE.md). Hosting/runbook: [DEPLOYMENT.md](docs/DEPLOYMENT.md). Complete acceptance mapping: [ACCEPTANCE.md](docs/ACCEPTANCE.md).
