# Architecture and data model

Laravel Blade renders crawlable pages; Livewire handles the calculator; Alpine is bundled for menus and modals; calculator pages use the Alpine runtime bundled with Livewire. Chart.js loads only when recorded chart data is available. Vite bundles Tailwind and Chart.js. The application uses Eloquent and Laravel's native MariaDB driver. UTC dates are stored in MariaDB; Pakistan timestamp displays use Asia/Karachi. Daily imported records display their UTC date convention explicitly.

Controllers coordinate requests. Form Requests validate external inputs. `Pricing` owns decimal calculations; provider adapters normalize rates; `FetchMarket` owns locks, quotas, snapshot transactions and error handling. Actions own dealer review, enquiry persistence and payment activation. Policies own dealer/product/lead access. Database queues own refresh, mail and alert work. Roles carry a static, reviewable permission list; public registrations always receive Customer access.

## Migrations

Base Laravel migrations create users/password reset tokens/sessions, cache/locks and database jobs/failed jobs. Platform migrations add indexed foreign-key records and user roles, active-state/TOTP fields. Follow-up migrations add observation resolution, composite indexes for public entitlement enforcement, and a separate alert reservation timestamp. Seven migration files create 44 tables.

| Area | Tables / relationships |
|---|---|
| Identity | users → roles; permissions stored as role JSON; Laravel password_reset_tokens and sessions |
| Geography | countries → cities; each dealer has a primary city/address rather than redundant one-location tables |
| Dealer operations | dealer_profiles → users/cities; dealer_documents; dealer_verifications → reviewer user; soft-deleted profiles |
| Listings | product_categories → products → dealer_profiles; product_images → product; soft-deleted products; content_reports |
| Metals | metal_types, metal_purities |
| Market | market_data_providers; provider_requests; fx_rate_snapshots; market_price_snapshots → FX/provider; daily_market_prices; market_data_errors; background_job_status |
| Local quotations | dealer_price_quotes → dealer (nullable for admin references)/operator; append-only, classified source/observation/expiry |
| Alerts | price_alerts → user; notification_logs uniquely pair alert and market snapshot |
| Buyer CRM | enquiries → dealer/optional product; enquiry_status_history → actor user; consent timestamp and keyed deduplication |
| Commercial | subscription_plans → subscriptions; invoices → dealer/plan; payments → invoice/recording user; sponsored_placements → subscription/dealer |
| Publishing | pages; blog_posts → author/category; blog_categories; per-record metadata consolidated into page/article fields |
| Operations | site_settings; audit_logs; analytics_events; Laravel jobs/failed_jobs/cache/cache_locks |

Subscription features are consolidated into explicit plan fields (product_limit, advanced_analytics, sponsored) for the MVP. Page/article SEO fields are consolidated into those records; generated directory metadata comes from approved profiles. Dealer primary locations are consolidated into dealer_profiles. Public plan enforcement is immediate, including after an expiry even before the hourly administrative status job runs. It retains excess records while excluding them from public listings.

Financial fields use MariaDB DECIMAL; calculations use Brick Math and BCMath. Financial display rounding also uses BigDecimal, preserving values larger than a PHP floating-point mantissa. Floating-point numbers are used only for Chart.js plotting. Full precision remains in snapshots and calculation results. Rate cards describe a 100% pure basis; the calculator applies actual purity.

Silver snapshots link to PKR FX and optional INR FX snapshots; FX has its own observed timestamp and source, so daily currency data is not represented as minute-by-minute forex. Gold API latest requests run centrally; cached display polling never calls external sources and does not expose a raw FX feed. Stale FX marks converted cards and prevents PKR alert evaluation/delivery.

Snapshot uniqueness is provider + metal + observed_at; FX uniqueness includes provider/time/currency pair. observed_at is separate from fetched_at. Historical date-only observations carry daily resolution/classification and cannot become intraday data or trigger alerts. Longer windows plot actual closing stored observations per UTC date, not interpolated prices. Daily OHLC fields aggregate the platform's stored observations, not an exchange-certified session OHLC feed.

## Isolation and security

- Public scopes require approved active dealers and moderated products within current plan allowances.
- Public forms never select a role or an entitlement. Dealer ownership is obtained from authenticated user relationships, not a submitted owner ID.
- Verification files use the private local disk. Only the owning dealer or authorized verified reviewer can download them; enabled reviewer TOTP is enforced on file access too.
- Uploaded photographs are decoded, resized and re-encoded as JPEG with opaque UUID names. Invalid/oversize/unsupported images fail validation. Verification PDFs/images remain private, served as attachments with no-store and nosniff headers.
- CMS text and user descriptions are escaped. External profile URLs accept HTTP/HTTPS only and use safe external-link attributes. No raw arbitrary HTML/script editor is exposed.
- Enquiry/analytic dedup keys are keyed hashes. Raw IP addresses and buyer contact details are not published in analytic records. Rate-limit keys use request IP transiently in the configured cache.
- Offline payments require administrator permission, an explicit confirmation, a unique receipt and an exact invoice amount. Database transactions lock the invoice and record activation/audit together.
- Alerts reserve a unique alert/snapshot record under a row lock. Frequency is checked before reservation. Queued delivery records sent, logged, retrying, failed or cancelled outcomes. Log-transport output does not set sent_at or last_notified_at. last_reserved_at controls the pending-notification cooldown.
- Provider credentials and TOTP seeds are encrypted using APP_KEY. Provider errors intentionally contain sanitized summaries instead of exception URLs carrying query-string keys.

One deployment/file cache is the MVP target. For multiple web nodes, choose a shared supported database cache/lock setup and shared file storage, test it, and avoid per-node quota locks. Quotas additionally reserve under a provider database row lock. No Redis requirement exists.
