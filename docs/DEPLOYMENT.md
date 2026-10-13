# cPanel / Apache deployment runbook

Production deployment to chandi.live through sarzone-live was explicitly authorized on 13 October 2026. Preserve the legacy project and its database. This market dashboard uses a separate application database and a public-only document root.

## Preflight

- Use a currently supported PHP runtime compatible with Laravel 12; PHP 8.3+ prepares for a future Laravel 13 upgrade. This delivery was tested on PHP 8.2.4. Confirm BCMath, PDO MySQL, GD JPEG/PNG, Fileinfo and normal Laravel extensions in both web and cron PHP.
- Provision a supported MariaDB LTS server (10.11+ recommended). Do not substitute SQLite or another database engine. cPanel's “MySQL Databases” UI may connect to MariaDB; verify `SELECT VERSION()` includes MariaDB.
- Create a separate database and user. Production credentials must not have access to the legacy or testing databases.
- Configure the domain document root to `/home/ACCOUNT/apps/chandi-live/public`. If cPanel cannot change the document root, ask the hosting operator for a supported vhost configuration; do not copy the entire application into an exposed `public_html` directory.
- Enable HTTPS. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://chandi.live`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `MARKET_DEMO=false`, `DB_CONNECTION=mariadb`, a secure `APP_KEY`, and actual DB/SMTP credentials. Leave `DB_SOCKET` empty for TCP. Private `.env` mode 0600; use server-managed secrets where supported.
- Set only verified reverse-proxy IPs in `TRUSTED_PROXIES` if required. Production host validation restricts requests to APP_URL's hostname. Check `/health` using the correct HTTPS hostname.
- Configure GD uploads, `upload_max_filesize=8M`, `post_max_size=48M`, memory 256 MB. Uploaded photographs are capped at 3000×3000 and resized to 1600 pixels; JPEG output strips metadata. Private verification uploads are separate. Ensure filesystem permissions permit only the application worker to write storage/bootstrap cache. Do not set the entire repository to 0777.

## Build and install

Build frontend assets in CI or locally with Node 22+. Deploy `public/build`; a Node process is not required on hosting.

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan key:generate  # First install only; preserve the existing key on upgrades.
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
php artisan chandi:admin your-admin@example.com
```

Reference seeding creates roles, cities, categories, plans and operational content only. It does not create dealers, administrator passwords or market prices. Seeders use `firstOrCreate`, preserving operator edits. Review plan entitlements and prices, legal texts, operating identity and contact fields before launch. Enable TOTP for administrators. SMTP and provider credentials remain in secure server configuration or encrypted credential storage.

## Public market feed

The local three-country feed uses `MARKET_PROVIDER=gold_api`, an enabled provider with a one-minute refresh, `MARKET_STALE_MINUTES=5` and `MARKET_FX_STALE_HOURS=48`. It stores real XAG/USD and separate daily PKR/INR FX observations. Keep the visible Gold API/ExchangeRate-API attribution and source timestamps. Do not expose the raw FX response. Latest pricing needs no key; optional paid history/Metals.Dev still needs credentials and suitable rights. Configure the production scheduler/worker below; the installed Mac crontab is local only.

## Cron and queues

Use the hosting panel's exact PHP binary path and application location. Add one scheduler entry:

```cron
* * * * * /usr/local/bin/php /home/ACCOUNT/apps/chandi-live/artisan schedule:run >> /home/ACCOUNT/logs/chandi-schedule.log 2>&1
```

A permanent worker is preferable if supported. On shared hosting, use a bounded worker each minute:

```cron
* * * * * /usr/bin/flock -n /home/ACCOUNT/apps/chandi-live/storage/chandi-worker.lock /usr/local/bin/php /home/ACCOUNT/apps/chandi-live/artisan queue:work database --stop-when-empty --max-time=50 --tries=3 --timeout=45 >> /home/ACCOUNT/logs/chandi-queue.log 2>&1
```

If `flock` is unavailable, have the host provide an equivalent single-worker lock. Avoid overlapping workers on restricted shared hosting. Jobs must complete within host limits; the market job's bounded timeout/backoff is under this allowance in ordinary provider operation. Adjust worker timeout consistently if the host terminates requests sooner. The application's central cache lock and database request reservation protect quotas independently of traffic.

Confirm `schedule:list`, a manually queued refresh, provider request logs and worker completion. A provider configured as disabled remains safely unavailable. Test history imports with licensed provider access. Set refresh frequency and local monthly quota below the actual provider allowance, accounting for other uses of the same key. Alerts only fire from fresh timestamped observations.

## Operations and logging

`/up` is Laravel's basic health route. `/health` checks MariaDB and reports market state (`recent`, `stale`, `demo` in development, or `unavailable`). Neither endpoint discloses credentials. Monitor non-200 health responses, failed jobs, market failure logs and repeated refresh failures. Configure `MARKET_ADMIN_EMAIL` for notifications. Store logs outside the document root and rotate them. Do not use local log mail for real customer communications.

Use `php artisan queue:failed` and `queue:retry ID` after correcting the cause. `queue:restart` allows long-running workers to pick up new code. Commercial confirmation records, review history and local reference quotes are append-only; correct errors through a documented operational review, not an unlogged database edit. Expired subscriptions lose advanced features, sponsorship and excess public listing visibility immediately through query enforcement; hourly expiry marks administrative status.

First-party analytic events store rotating session hashes, not raw IPs. Recorded daily sessions include bots and local QA requests; they are not automatically identified as organic human visitors. File caches and locks are isolated by APP_ENV. DNT disables recording. `chandi:prune` removes analytics older than 90 days and local request logs older than 12 months. Decide documented retention for buyer contacts, verification files, financial evidence and audit records before public launch. Account deactivation is distinct from a legal deletion request.

## Backups and rollback

Back up the new database, public uploads, private verification files and the APP_KEY/secrets separately, with access-controlled encrypted storage. The key is necessary to decrypt TOTP and provider credentials. Use a protected MariaDB client option file or a host-managed backup job; do not place passwords in command arguments or shell history. Verify restoration into an isolated database on a schedule.

Before an upgrade, record the release revision, create a database/file backup and confirm a known-good asset bundle. Put the application in maintenance mode if a migration requires it. Run `php artisan migrate --force`, then `optimize`, restart workers and verify public, dealer, admin and health routes.

For rollback, restore the previous code/assets first only if its schema remains compatible. Otherwise restore the matching database snapshot and uploaded-file backup in a maintenance window, then rebuild caches. Avoid `migrate:rollback` against populated production data without reviewing each migration's down method and verifying a restorable backup. **Never use `migrate:fresh` outside disposable databases.**

## Launch checklist

- New document root/private-file denial checked from outside; `.env`, vendor, private documents and logs inaccessible.
- HTTPS, secure encrypted sessions, production debug off, trusted proxies/host, SMTP verification and password-reset email checked.
- MariaDB LTS migrations and tests run against an isolated database on the actual PHP/runtime; production schema migration succeeds.
- Provider rights/keys, quote classification, observed/fetched dates, stale fallback and quota settings checked against a real response.
- No demo data in public production results; real dealers and uploaded documents reviewed by an actual operator.
- Registration, moderation, enquiries, unsubscribe, TOTP, manual invoice confirmation and expiration checked by the operating team.
- Search Console token, canonical domain, sitemap, appropriate indexing, legal operator/contact/retention and legal texts reviewed.
- Scheduler, queue, monitoring, backups and restore drill confirmed.
- Performance and accessibility audited on the actual hosting and real content; no Lighthouse or WCAG certification is claimed by this delivery.


## Market-only launch configuration

Keep `MARKETPLACE_ENABLED=false` for this release. After migrations, run `market:backfill FROM TO` for up to 366 completed days to import the public Currency API daily reference archive, and `market:references` to fetch PSL and recent daily references. Schedule `market:references` in addition to `market:fetch`; the existing local `scripts/market-refresh.sh` already does both. Source checks are centrally cached/locked, never triggered by visitors. Retain outbound HTTPS access to `cdn.jsdelivr.net`, dated `currency-api.pages.dev` hosts, and `www.goldtoday.io`. The archive provider stays disabled for live fetch selection and is excluded from live-card queries.

The PSL source unit is currently unconfirmed. Preserve the published-unit disclosure until the source establishes a reliable definition; no current PSL per-gram or per-tola conversions should be exposed. Earlier PSL history is not available from this source.
