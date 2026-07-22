# Laravel Technical Assessment — CSV to Shopify Product Import System

> Transcribed from the recruiter's PDF brief (Vrhunec). The emailed PDF is the authoritative source.

## Project Requirements

Create a Laravel 12 application that allows users to upload CSV files containing product data, process them asynchronously, import the products to Shopify, and track the import status.

*(We scaffolded on Laravel 13 / latest and asked the recruiter to confirm whether latest is acceptable or Laravel 12 is strictly required.)*

## Task Breakdown

### 1. Project Setup
- Initialize a new Laravel project
- Configure database connections
- Set up Shopify API credentials in the environment file
- Install necessary packages

### 2. File Upload Interface
- Create a form that allows users to upload CSV files
- Implement client-side validation for file type and size
- Design a clean, responsive interface
- Add feedback mechanisms for successful uploads

### 3. CSV Processing and Shopify Integration
- Create a database migration to save product records
- Implement a Laravel job for processing CSV files asynchronously
- Parse CSV data and validate the format
- Map CSV columns to Shopify product fields
- Create API integration with Shopify to add products

### 4. Import Status Tracking (per brief; confirm details vs PDF page 3)
- Track status of each import run (pending / processing / completed / failed)
- Record per-row success/failure and errors
- Surface status to the user

## Provided by recruiter

- **Shopify Sandbox Store URL:** https://laravel-import-test.myshopify.com/
- **Collection ID:** 464337174767 — **products must be added to this collection**
- **Admin API access token:** provided in the email → stored in `.env` as `SHOPIFY_ACCESS_TOKEN` (secret, not committed)
- **Sample data:** `shopify-products-csv.csv` (10 products, standard Shopify product CSV columns) — download from the email into `storage/app/samples/`.

## Reply commitment

- Estimated hours: ~12
- Submission: by end of day 2026-07-23
- Open clarifications sent to recruiter: (1) Laravel 12 strict vs latest? (2) React+Inertia upload page + Filament admin panel — acceptable?
