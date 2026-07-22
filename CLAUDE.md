# CSV → Shopify Product Import System

Technical assessment for a **Senior Backend (PHP/Laravel)** role (Vrhunec).
Build a Laravel app that uploads a CSV of products, processes it **asynchronously**, imports the products into **Shopify** (into a specific collection), and **tracks import status**.

> Full original brief: `docs/ASSESSMENT.md`. Source of truth is the "Laravel Technical Assessment" PDF the recruiter emailed (keep it).

## Stack (decisions)

- **Laravel 13** (latest) — installer scaffolded this. NOTE: the brief said "Laravel 12"; we asked the recruiter whether latest is acceptable — confirm their answer and downgrade only if they require 12.
- **React + Inertia** (Laravel React starter kit) — already installed — for the **CSV upload page** (client-side file-type/size validation, responsive, upload feedback).
- **Filament** (admin panel) — for **import runs / status tracking / imported-product views**. NOT installed yet — `composer require filament/filament` then `php artisan filament:install --panels`. (Also pending recruiter's OK on this stack; asked in the reply.)
- Queue: database driver is fine (`QUEUE_CONNECTION=database`; run `php artisan queue:table && migrate`), or Redis if available.
- Shopify: Admin REST or GraphQL Admin API. Either raw HTTP client or a package (e.g. `shopify/shopify-api` or Laravel HTTP client). Keep it simple + well-structured.

## Credentials (already in `.env`, gitignored — NEVER commit)

- `SHOPIFY_STORE_URL` — sandbox store (laravel-import-test.myshopify.com)
- `SHOPIFY_ACCESS_TOKEN` — Admin API access token (**secret** — sandbox only)
- `SHOPIFY_API_VERSION` — 2025-07
- `SHOPIFY_COLLECTION_ID` — products MUST be added to this collection

`config/shopify.php` exposes these. `.env.example` has placeholders for anyone cloning.

## Sample data

Recruiter attached `shopify-products-csv.csv` (10 products: Handle, Title, Body HTML, Vendor, Product Type, Tags, Published, Variant SKU/Price/Compare-at/Requires-shipping/Taxable/Inventory-tracker/Inventory-qty/Inventory-policy, …). **Download it from the Vrhunec email into `storage/app/samples/shopify-products-csv.csv`** (not committed) and use it to test. It follows the standard Shopify product CSV column layout.

## Build checklist (what's left)

1. **Project setup** — [done] Laravel 13 + React/Inertia scaffolded, `.env` Shopify creds set. TODO: `config/shopify.php`, install Filament, set `QUEUE_CONNECTION=database` + queue table.
2. **Upload interface (React/Inertia)** — form to upload CSV; client-side validation (type = .csv, max size); responsive; success/error feedback; on submit, store file + dispatch import job.
3. **CSV processing + Shopify integration**
   - migration(s): `import_runs` (status, totals, counts, errors, timestamps) and `products` (imported product records + shopify_product_id).
   - queued Job: parse CSV (stream/League\Csv), validate rows, map CSV columns → Shopify product fields, create products via Shopify Admin API, attach each to `SHOPIFY_COLLECTION_ID`, record per-row result.
   - handle Shopify rate limits + partial failures; make the job idempotent/retriable.
4. **Import status tracking** — persist per-run status (pending/processing/completed/failed) + per-row outcome; surface in Filament (list of import runs, drill into rows/errors) and/or an Inertia status page that polls.
5. **Polish** — validation messages, error states, a clear `README.md` (setup + run steps: `composer install`, `npm install && npm run build`, migrate, `queue:work`, how to upload), seed/sample instructions.

## Deliverables (standard — confirm vs PDF page 3)

- Public GitHub repo (this one) with clean commit history.
- README with setup/run instructions.
- Working upload → async import → products live in the sandbox collection.
- Estimated effort quoted to recruiter: **~12 hours**, submission **by EOD 2026-07-23**.

## Conventions

- Follow Laravel + this repo's Pint config; run `./vendor/bin/pint` before commits.
- Tests: Pest is set up (`php artisan test`); cover the CSV parser + column mapper at minimum.
- Never commit `.env` or the access token. Sandbox creds only.
