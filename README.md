# Store POS and accounting platform

Arabic RTL web platform for four cashier stations, connected stock, purchases, expenses and employee advances. The first release is online-only and must issue official Saudi invoices. Opening target: 1 November 2026.

**Status:** staff login and roles, four stations, catalogue/barcode baskets, quantity-only opening stock, development sale posting, cash/card/split payment records, stock deductions and a balanced sales/VAT journal are implemented. Central sale history preserves document snapshots. Official invoicing, printing, stock valuation, purchases, returns and drawer closing are not implemented. Sale posting is restricted to local/test environments until official issuance is connected. VAT registration is confirmed; company-specific Phase 2 details and physical device compatibility remain unverified.

Selected stack: **Laravel + React/TypeScript through Inertia + PostgreSQL**, using Tailwind and shadcn/ui for shared Arabic RTL components. Versions, rationale and integration boundaries are maintained in the [Engineering guide](docs/engineering-guide.md#selected-stack).

## Document map

| Document | Responsibility |
| --- | --- |
| [AGENTS.md](AGENTS.md) | Shared working instructions for coding agents |
| [CLAUDE.md](CLAUDE.md) | Imports the shared instructions for Claude Code |
| [MVP workflows](docs/store-mvp-workflows.md) | Product scope, transaction effects, migration needs and acceptance outcomes |
| [Engineering guide](docs/engineering-guide.md) | Module boundaries, reuse, Arabic RTL UX, styling and growth strategy |
| [Reference review](docs/reference-system-review.md) | Evidence from Technolink; historical observations, not product authority |

Read the relevant section instead of loading every document for every task. Keep each decision in its owning document and link to it from elsewhere. Setup and verification commands below use the checked Docker development environment.

## Working approach

Use one modular application and a relational database. Deliver small complete workflows. Share real business rules and reusable UI components; add infrastructure and abstractions only when a present need justifies them.

Write the product in Arabic directly. Prioritize clear workflows and reliable transactions. A small theme-token layer and shared components will make later visual changes easier without delaying the functional foundation.

## Local development

Requires Docker with Compose. The container provides PHP 8.4, Composer and Node 22; PostgreSQL 18 runs separately. This Compose configuration is for development and binds the web port to localhost.

1. Copy `.env.example` to `.env`. Set `DB_PASSWORD` to a newly generated random value, for example using `openssl rand -hex 24`. Keep `.env` private.
2. Build the runtime and install the locked dependencies:

```sh
docker compose build
docker compose run --rm --no-deps app composer install --no-interaction
docker compose run --rm --no-deps app npm ci
docker compose run --rm --no-deps app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate --seed
docker compose exec app npm run build
```

3. Create a staff account interactively; choose the owner role for initial administration:

```sh
docker compose exec app php artisan store:create-user
```

Open [the local application](http://localhost:8000). Public registration is disabled. Normal seeding creates the four station records without creating users or importing store data.

Optional fictional catalogue for a local demo:

```sh
docker compose exec app php artisan db:seed --class=DemoProductSeeder
```

Demo barcodes are `000001`, `000002` and `000003`. The demo seeder refuses non-local/non-test environments. No fixed staff password is committed. For this workstation's prepared demo, the generated login is in the ignored `storage/app/private/development-login.txt` file.

For the demo, use **المخزون** to enter a counted opening quantity and its date/reference for a fictional product. Each product accepts one opening entry. Scan it in **نقطة البيع**, choose **تسجيل الدفع**, review cash/change or a manually confirmed external card result, then save the development sale. **المبيعات** shows the saved record, payment allocations and (for owner/accountant) its journal. These records are internal and are not official invoices. No card charge is executed by this application.

After frontend changes, rebuild with `docker compose exec app npm run build` and reload the browser. For hot reload, run `docker compose exec app npm run dev -- --host 0.0.0.0`; keep that process running. Wayfinder route bindings are generated during the build and are not edited or committed manually.

Stop development services with `docker compose stop`. Database data lives in the named Compose volume; removing that volume discards it.

## Verification

Create the isolated test database once:

```sh
docker compose exec database createdb -U tli_store tli_store_test
```

Run the checks:

```sh
docker compose exec app composer test
docker compose exec app npm run check
docker compose exec app npm run types:check
docker compose exec app npm run build
```

The test configuration forces PostgreSQL database `tli_store_test`; the feature-test base class also checks that name. Feature tests rebuild only that dedicated database. Never point it at operational data. Backend checks include Pint and PHPStan. Tests cover permissions, barcode identity, decimal rounding, authoritative totals, split payment/change, snapshots, duplicate requests, rollback, one-time opening stock and concurrent posting. Four independent PHP processes compete for one unit in the dedicated PostgreSQL test database; only one may commit. Simultaneous retries must return the same sale.

Manual browser checks cover Arabic RTL, scan focus, basket drafts, opening stock and the development payment-to-sale workflow. Database concurrency tests complement practical acceptance on four actual devices; real scanner/printer, fiscal processing and backup restoration remain release work.

## Current boundaries and next slice

Baskets are drafts in this browser tab's session storage, scoped by staff user and station. They survive reload and navigation but do not synchronize between devices and are not offline sales. The server re-reads catalogue prices and tax rates for every preview. Preview requests create no sale, invoice, payment, journal or stock movement. Returning from payment review refreshes the quote so changed catalogue data can be reviewed again.

Initial preview policy: tax-inclusive product prices; quantity up to three decimal places; each line's gross and net rounded half-up to two decimals; tax is gross minus net; totals sum the rounded lines. This is not a claim of ZATCA compliance. Tax classification and fiscal rounding must be reviewed when official invoicing is implemented.

Saving a development sale commits its snapshots, applied payments, stock deduction and sales/VAT journal in one database transaction. Cash change is excluded from the sale payment; external card results are recorded manually into a card-clearing account. Products/balances are locked in a consistent order. The server blocks insufficient stock, stale quotes and unconfirmed card allocations. Cashiers can read their own sales; owner/accountant can read all sales and journals.

An operation UUID is persisted in session storage before submission. An uncertain response freezes that attempt; recovery reuses its exact payload, even after catalogue deactivation. A completed retry returns the original sale. Do not close the tab or clear its storage during recovery; those actions lose the local recovery identity. Saved records remain in the central sales list. There is no delete/edit endpoint for posted sales.

Opening entries establish quantities only, with an actor, date and source reference. The [stock policy](docs/store-mvp-workflows.md#sale-scan-to-central-records) and [undecided cost method](docs/store-mvp-workflows.md#data-relationships-and-posting-outcomes) are product decisions. Unknown costs are not treated as zero; no inventory value, cost of goods sold or profit is calculated. Opening corrections, later receipts and adjustments need their own linked workflows.

Next connect official issuance and receipt printing, including their recovery states, before enabling production checkout. Cashier shifts/drawer closing and the remaining accounting workflows still need implementation. Validate that complete workflow on real POS hardware before launch.

The application foundation was adapted from the [official Laravel React starter](https://github.com/laravel/react-starter-kit), under its MIT license. The shared working instructions remain in AGENTS.md and CLAUDE.md.
