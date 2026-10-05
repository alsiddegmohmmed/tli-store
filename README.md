# Store POS and accounting platform

Arabic RTL web platform for four cashier stations, connected stock, purchases, expenses and employee advances. The first release is online-only and must issue official Saudi invoices. Opening target: 1 November 2026.

**Status:** first working foundation: staff login and roles, four station records, product creation/search, barcode entry, station-specific browser baskets and server-calculated previews. Checkout, stock movements, accounting posting, official invoicing and printer integration are not implemented. VAT registration is confirmed; company-specific Phase 2 details and physical device compatibility remain unverified.

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

The test configuration forces PostgreSQL database `tli_store_test`; the feature-test base class also checks that name. Feature tests rebuild only that dedicated database. Never point it at operational data. Backend checks include Pint and PHPStan; meaningful tests cover authentication, staff creation, permissions, barcode identity, decimal rounding and authoritative preview totals.

Manual browser checks cover Arabic RTL, scan focus, repeated scans, name search, fractional quantities, station switching and basket restoration after reload. Real scanner/printer behavior and simultaneous posting from four devices remain release acceptance work.

## Current boundaries and next slice

Baskets are drafts in this browser tab's session storage, scoped by staff user and station. They survive reload and navigation but do not synchronize between devices and are not offline sales. The server re-reads catalogue prices and tax rates for every preview. Preview requests create no sale, invoice, payment, journal or stock movement.

Initial preview policy: tax-inclusive product prices; quantity up to three decimal places; each line's gross and net rounded half-up to two decimals; tax is gross minus net; totals sum the rounded lines. This is not a claim of ZATCA compliance. Tax classification and fiscal rounding must be reviewed when official invoicing is implemented.

Next implement one committed sale with payments, stock and accounting effects in an atomic transaction, duplicate-submission protection and immutable snapshots; then connect fiscal issuance and receipt printing. Validate that complete workflow on a real POS before expanding release acceptance to all four devices.

The application foundation was adapted from the [official Laravel React starter](https://github.com/laravel/react-starter-kit), under its MIT license. The shared working instructions remain in AGENTS.md and CLAUDE.md.
