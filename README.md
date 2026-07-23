# CSV → Shopify Product Import

A Laravel application that uploads a CSV of products, processes it **asynchronously** on a queue, imports each product into **Shopify** via the **GraphQL Admin API** (adding it to a specific collection), and **tracks the status** of every run down to the individual row — with full event logging and failure notifications.

Built for the Vrhunec technical assessment (CSV to Shopify Product Import System).

## Features

### Core

- **Upload UI** (React + Inertia): drag-and-drop CSV upload with client-side type/size validation and live upload feedback.
- **Asynchronous import** (queued job): streams the CSV, validates rows, maps columns, and imports into Shopify.
- **Shopify GraphQL integration**: products are created via the Admin GraphQL API using the official `shopify/shopify-api` SDK.
- **Status tracking**: per-run status (`pending → processing → completed/failed`) and per-row status (`pending / processing / successful / failed`) with error messages.
- **Dashboard**: an Inertia list + live status page, plus a full **Filament v5** admin panel (import runs, rows, logs, stats).

### Bonus features (all implemented)

- **Update product (upsert)**: products are matched by `handle` — an existing product is **updated** instead of duplicated (GraphQL `productSet`).
- **Logging**: every import event is written to an `import_logs` table — the in-dashboard **log viewer** (Filament "Import Logs", plus a per-run log tab) — **and** to a dedicated `import` log channel on disk (`storage/logs/import-*.log`).
- **Error notification system**: when a run finishes with failed rows (or fails outright), admin users receive a **Filament database notification** (the panel bell).
- **GraphQL**: the entire Shopify integration uses GraphQL queries/mutations with GraphQL error handling (`userErrors` + throttling/`THROTTLED` back-off).

### Resilience

- Rate-limit handling: the SDK retries `429`/`5xx`; GraphQL `THROTTLED` responses back off using the returned query cost.
- Partial-failure isolation: one bad row never aborts the run.
- Idempotent & retryable: rows are keyed by number, already-successful rows are skipped, and `productSet` upserts never duplicate a product.

## Tech stack

| Concern            | Choice                                                        |
|--------------------|---------------------------------------------------------------|
| Framework          | Laravel 13, PHP 8.3+                                          |
| Frontend           | React 19 + Inertia (Laravel React starter kit)                |
| Admin panel        | Filament v5                                                   |
| Shopify            | Admin **GraphQL** API via `shopify/shopify-api` SDK           |
| Queue              | Database driver                                               |
| Database           | SQLite (default; any Laravel-supported DB works)              |
| CSV parsing        | `league/csv` (streaming) — see note below                     |
| Logging / viewer   | `import_logs` table (Filament log viewer) + `import` log channel on disk |
| Tests / quality    | PHPUnit, Pint, Larastan (PHPStan level 7)                     |

## Architecture

```
Upload (React/Inertia)
      │  POST /imports  (StoreImportRequest validates the file)
      ▼
ImportController@store ──► stores file, creates ImportRun (pending) ──► dispatches ProcessImportRun
                                                                              │
                                                                              ▼
                                                        ProcessImportRun (queued)
                                                          • ProductCsvReader     – stream rows
                                                          • ShopifyProductMapper  – CSV → canonical product
                                                          • ProductRowValidator   – validate each row
                                                          • ShopifyProductImporter – upsert via GraphQL + add to collection
                                                          • ImportLogger          – log every event (DB + file)
                                                          • records ImportedProduct per row, updates counters
                                                          • ImportFailuresNotification on failures
      ▲                                                                         │
      │  GET /imports/{run}/status  (ImportRunResource JSON, polled)            │
      └─────────────────────────── status page / Filament admin ◄──────────────┘
```

Key classes:

- `app/Http/Requests/StoreImportRequest.php` — upload validation (Form Request).
- `app/Jobs/ProcessImportRun.php` — the asynchronous import orchestration.
- `app/Services/Shopify/Contracts/ShopifyProductImporter.php` — Shopify integration contract.
- `app/Services/Shopify/GraphqlShopifyImporter.php` — GraphQL implementation (upsert + collection + throttling).
- `app/Services/Import/ImportLogger.php` — writes events to `import_logs` + the `import` log channel.
- `app/Notifications/ImportFailuresNotification.php` — admin failure notifications.
- `app/Support/Shopify/ShopifyProductMapper.php` — CSV columns → canonical product (tolerant of header casing/punctuation).
- `app/Http/Resources/*` — JSON API resources for all API output.
- `app/Filament/Resources/*`, `app/Filament/Widgets/*` — admin panel (runs, logs, stats).

## Requirements

- PHP 8.3+
- Composer
- Node.js 20+ / npm
- A Shopify store + Admin API access token (a sandbox is used for the assessment)

## Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Add your Shopify credentials to .env (see below)

# 4. Database (creates app, queue, cache, session, notification, log tables)
touch database/database.sqlite   # if using the default SQLite DB
php artisan migrate

# 5. Seed a demo login user (test@example.com / password)
php artisan db:seed

# 6. Build the frontend
npm run build
```

### Shopify credentials (`.env`)

```dotenv
SHOPIFY_STORE_URL=https://your-store.myshopify.com
SHOPIFY_ACCESS_TOKEN=shpat_xxx        # Admin API access token — secret, never commit
SHOPIFY_API_VERSION=2025-07
SHOPIFY_COLLECTION_ID=464337174767    # products are added to this collection

QUEUE_CONNECTION=database
```

`.env` is git-ignored; only sandbox credentials are used.

## Running

Run the web server and a queue worker (the queue worker is required for imports to process):

```bash
php artisan serve            # http://localhost:8000
php artisan queue:work       # processes ProcessImportRun jobs
```

Or use the bundled dev script (server, queue, logs, vite together):

```bash
composer dev
```

### Using the app

1. Log in at `/login` with `test@example.com` / `password`.
2. Go to **Imports → New import** (`/imports/create`) and upload a CSV.
3. You're redirected to the run's status page, which polls until the import finishes and links each product into Shopify.
4. Review runs, per-row results, the event log (Import Logs), and stats in the admin panel at `/admin`.

A ready-to-use sample CSV (the assessment's 10 products, with images and weights) is committed at [`samples/shopify-products-csv.csv`](samples/shopify-products-csv.csv) — upload it as-is to try the flow.

## CSV format

Standard Shopify product export columns. The mapper is tolerant of header casing and punctuation (e.g. both `Body (HTML)` and `Body HTML`, `Type` and `Product Type` work). Recognised columns:

`Handle, Title, Body (HTML), Vendor, Type, Tags, Published, Variant SKU, Variant Price, Variant Compare At Price, Variant Requires Shipping, Variant Taxable, Variant Inventory Tracker, Variant Inventory Qty, Variant Inventory Policy, Variant Fulfillment Service, Variant Weight, Variant Weight Unit, Image Src, Image Position, Image Alt Text`

Each row requires at least a **Title** and a numeric **Variant Price**; rows that fail validation are recorded as failed without aborting the run. A missing `Handle` is derived from the title.

## Testing

```bash
php artisan test           # PHPUnit feature + unit tests
./vendor/bin/pint --test   # code style
./vendor/bin/phpstan analyse
npm run types:check        # TypeScript
npm run lint:check         # ESLint
```

Tests cover the column mapper (incl. images/weight/fulfillment), the GraphQL `productSet` input builder, the row validator, the streaming CSV reader, the import job (success, validation failure, Shopify failure, upsert/update, idempotent retry, event logging, failure notification, fatal failure), the upload endpoint, the JSON status endpoint, and the Inertia pages. The job tests bind an in-memory `ShopifyProductImporter`, so they run without hitting Shopify.

## Assumptions & design decisions

- **Laravel 13 instead of 12.** The brief asked for Laravel 12; the current React starter kit ships on Laravel 13, and the recruiter was asked whether latest is acceptable. All APIs used are stable across 12/13.
- **GraphQL over REST.** The bonus asked for GraphQL, so the *only* Shopify integration is GraphQL (`shopify/shopify-api` SDK), behind a `ShopifyProductImporter` interface so the transport is swappable and easily faked in tests.
- **Upsert by handle.** `productSet` with a `handle` identifier creates or updates, satisfying the "update product if it exists" bonus and making retries safe.
- **Custom-app token auth.** The SDK sends the fixed Admin API access token as `X-Shopify-Access-Token` (`isPrivateApp: false`); the API key/secret passed to `Context::initialize` are unused placeholders.
- **Single default variant per row.** The standard Shopify product CSV is one variant per row, mapped to the `Default Title` option. Multi-variant/option rows are out of scope.
- **Inventory quantity** is not set via GraphQL (it requires a location-scoped inventory mutation); price, SKU, policy, taxable, weight, and images are.
- **`maatwebsite/excel` was evaluated but not used:** its `phpspreadsheet` dependency requires PHP `< 8.5` and it targets Laravel ≤ 12, so it is incompatible with this stack (PHP 8.5 / Laravel 13). `league/csv` provides the same clean, streaming API and is used instead.
- **Auth.** Import and admin routes are authenticated (the starter kit's Fortify auth); a demo user is seeded.

## Demo video

A recorded walkthrough is included as [`demo.mp4`](demo.mp4) — it covers login, the live dashboard, upload with client-side validation, the asynchronous import updating live, per-row results, **light and dark mode**, and the Filament admin panel (runs, event log, chart, notifications).

It is generated automatically (Playwright screen recording with an on-screen click indicator and step captions, converted to MP4 with ffmpeg). To regenerate:

```bash
# one-time: npm i -D playwright && npx playwright install chromium   (ffmpeg also required)
php artisan serve            # in one terminal
php artisan queue:work       # in another
./scripts/record-demo.sh     # writes demo.mp4
```
