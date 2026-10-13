# CHANDI.LIVE production

Deployed on 13 October 2026 using the explicitly authorized `sarzone-live` SSH connection and Git repository `https://github.com/ceosarzone/chandi_live.git` (`main`).

- Public URL: https://chandi.live/ (TLS verified; HTTP redirects to HTTPS).
- Application checkout: `/home/mshah01/apps/chandi-live/app`.
- Domain directory: `/home/mshah01/chandi.live`, symlinked to the checkout's **public** directory. Application source, vendor, credentials and private storage are outside the document root.
- The previously empty document-root directory is retained at `/home/mshah01/apps/chandi-live/previous-document-root`.
- Production environment: private `/home/mshah01/apps/chandi-live/.env`, linked into the checkout. Never commit or copy it to public storage. A separate production application key was generated.
- Runtime: PHP 8.2.34; MariaDB 10.6.28; Laravel 12. The shared server runtime and other hosted sites were not changed. MultiPHP management is unavailable to this hosting account.
- Dedicated database and database user, restricted to this application's database. Nine migrations applied. Only reference seed data and genuine market observations were loaded; no demo users or default administrator were created.
- `APP_ENV=production`, `APP_DEBUG=false`, encrypted secure session cookies, `MARKETPLACE_ENABLED=false` and `MARKET_DEMO=false`.
- Frontend build produced locally with the lockfile and copied into `public/build` (the server does not need Node).

## Automatic updates

One application-specific minute crontab entry runs `scripts/market-refresh.sh` with `/usr/local/bin/php`, protected by `/usr/bin/flock`. It refreshes spot, checks reference feeds and drains the database queue. PSL checks are limited to every 15 minutes; recent daily archive dates are checked daily. Existing account cron entries are preserved. The previous crontab is backed up privately at `../crontab.before-chandi`.

Logs: `../market-cron.log` and `storage/logs/laravel.log`. The archive has 364 daily records from 2025-10-13 through 2026-10-12; 2025-12-10 is unavailable from both upstream archive URLs. Preserve gaps and the PSL unit disclosure.

## Updating with Git

1. Test and build locally, commit, and push `main`. Transfer only the matching `public/build` artifact; credentials, vendor, test data and runtime files are never uploaded to GitHub.
2. Over `ssh sarzone-live`, record the existing revision and take a protected database backup before schema changes. In the checkout, run `git pull --ff-only` and `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` if dependencies changed.
3. Install the matching asset build. Apply necessary migrations with `php artisan migrate --force`, then run `php artisan optimize` and `php artisan queue:restart`.
4. Verify HTTPS `/health`, current quote timestamps, all three histories, themes and file-denial checks. Production browser checks require explicitly setting `CHANDI_LIVE_URL=https://chandi.live`.

For rollback, restore a previously verified code revision and its matching asset bundle. Preserve the production environment, storage and application key. Review schema compatibility before rollback; never use `migrate:fresh` or blindly roll back populated migrations. The initial public-directory switch can be reversed by restoring the retained previous directory, which was empty.

## Remaining optional configuration

SMTP and administrator access were not provisioned: this release is a public market display with registration disabled. No mail was sent. The server's shared PHP/MariaDB version was retained; future runtime upgrades should be coordinated with the hosting operator. The PSL source unit remains unconfirmed and is labelled accordingly.

## Footer visitor counter

The footer displays the cumulative number of recorded daily browser sessions, not unique people. Existing `platform_visit` records initialize `visitor_totals`; each new deduplicated event increments that total atomically in the same transaction. Repeated requests in the same session/day do not increment it. The aggregate survives the 90-day analytics retention cleanup. Successful public HTML visits count; chart/rates polling, private routes, recognized bot user agents and Do Not Track requests do not. The footer reads completed visits, so a newly recorded visit appears on the next page load. No IP addresses or new tracking cookies are stored. Clearing session cookies or returning on a later day can count again.
