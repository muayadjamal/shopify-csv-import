# CSV → Shopify Product Import

A Laravel application that uploads a CSV of products, processes it **asynchronously** on a queue, imports each product into **Shopify** (adding it to a specific collection), and **tracks the status** of every run down to the individual row.

Built for the Vrhunec Senior Backend (PHP/Laravel) technical assessment.

## Features

- **Upload UI** (React + Inertia): drag-and-drop CSV upload with client-side type/size validation and live upload feedback.
- **Asynchronous import** (queued job): streams the CSV, maps columns to the Shopify Admin API, creates products, and attaches each to the configured collection.
- **Status tracking**: per-run status (`pending → processing → completed/failed`), row counts, and a per-row result (success/failed with the Shopify product id or error message).
- **Live status page**: polls a JSON API endpoint until the run finishes; links each imported product to its Shopify admin page.
- **Admin panel** (Filament v5): browse import runs, drill into a run, and inspect every imported row.
- **Resilience**: Shopify rate-limit handling (HTTP 429 + `Retry-After`), partial-failure isolation (one bad row never fails the run), and an idempotent, retryable job.

## Tech stack

| Concern            | Choice                                            |
|--------------------|---------------------------------------------------|
| Framework          | Laravel 13, PHP 8.3+                              |
| Frontend           | React 19 + Inertia (Laravel React starter kit)    |
| Admin panel        | Filament v5                                       |
| Queue              | Database driver                                   |
| Database           | SQLite (default; any Laravel-supported DB works)  |
| CSV parsing        | `league/csv` (streaming)                          |
| Shopify            | Admin REST API via Laravel HTTP client            |
| Tests / quality    | PHPUnit, Pint, Larastan (PHPStan level 7)         |

## Architecture

```
Upload (React/Inertia)
      │  POST /imports  (StoreImportRequest validates the file)
      ▼
ImportController@store ──► stores file, creates ImportRun (pending) ──► dispatches ProcessImportRun
                                                                              │
                                                                              ▼
                                                        ProcessImportRun (queued)
                                                          • ProductCsvReader   – stream rows
                                                          • ShopifyProductMapper – CSV → API payload
                                                          • ProductRowValidator  – validate each row
                                                          • ShopifyClient        – create product + attach to collection
                                                          • records ImportedProduct per row, updates run counters
      ▲                                                                         │
      │  GET /imports/{run}/status  (ImportRunResource JSON, polled)            │
      └─────────────────────────── status page / Filament admin ◄──────────────┘
```

Key classes:

- `app/Http/Requests/StoreImportRequest.php` — upload validation (Form Request).
- `app/Jobs/ProcessImportRun.php` — the asynchronous import.
- `app/Services/Shopify/ShopifyClient.php` — Admin API client with rate-limit handling.
- `app/Support/Csv/ProductCsvReader.php` — streaming CSV reader.
- `app/Support/Shopify/ShopifyProductMapper.php` — CSV columns → Shopify product payload (tolerant of header casing/punctuation).
- `app/Support/Shopify/ProductRowValidator.php` — per-row validation.
- `app/Http/Resources/*` — JSON API resources for all API output.
- `app/Filament/Resources/ImportRuns/*` — admin panel.

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

# 4. Database + queue + cache tables
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
4. Review runs and per-row results in the admin panel at `/admin`.

A ready-to-use sample CSV (5 products) is committed at [`samples/shopify-products-csv.csv`](samples/shopify-products-csv.csv) — upload it as-is to try the flow.

## CSV format

Standard Shopify product export columns. The mapper is tolerant of header casing and punctuation (e.g. both `Body (HTML)` and `Body HTML`, `Type` and `Product Type` work). Recognised columns:

`Handle, Title, Body (HTML), Vendor, Type, Tags, Published, Variant SKU, Variant Price, Variant Compare At Price, Variant Requires Shipping, Variant Taxable, Variant Inventory Tracker, Variant Inventory Qty, Variant Inventory Policy`

Each row requires at least a **Title** and a numeric **Variant Price**; rows that fail validation are recorded as failed without aborting the run. A missing `Handle` is derived from the title.

## Testing & quality

```bash
php artisan test           # PHPUnit feature + unit tests
./vendor/bin/pint --test   # code style
./vendor/bin/phpstan analyse
npm run types:check        # TypeScript
npm run lint:check         # ESLint
```

Tests cover the column mapper, row validator, streaming CSV reader, the import job (success, partial failure, idempotent retry, and fatal failure), the upload endpoint (validation + job dispatch), the JSON status endpoint, and the Inertia pages.

## Notes / decisions

- The import job is **idempotent**: per-row results are keyed by row number, already-successful rows are skipped on retry, and run counters are recomputed from the stored results rather than incremented — so a retried job never double-imports or double-counts.
- Rate limits are handled by retrying `429` responses and honouring the `Retry-After` header.
- All API responses use Laravel **JSON API resources** (`ImportRunResource`, `ImportedProductResource`); validation lives in a **Form Request**.
- Import runs are created only by uploading a CSV — the admin panel is read-only for runs.
