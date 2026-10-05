# Engineering guide

Status: the foundation and development-only sale posting slice are implemented and locally verified; later module boundaries remain proposed. Current feature boundaries and setup commands are in [README](../README.md). Audience: developers and coding agents. Product behavior belongs in [MVP workflows](store-mvp-workflows.md); task execution rules belong in [AGENTS.md](../AGENTS.md).

## Selected stack

Selected on 5 October 2026 under the owner's request to choose the stack. The first slice uses the official Laravel React starter. Keep one repository and one application deployment, with modules inside it.

| Layer | Selection | Purpose |
| --- | --- | --- |
| Backend | Laravel 13 on PHP 8.4 | Business rules, authorization, validation, transactions, migrations and integrations |
| UI | React 19 and TypeScript through Inertia 3 | Interactive POS and reusable forms/components, served by Laravel routes |
| Styling | Tailwind CSS 4 and shadcn/ui | Shared primitives, semantic theme tokens and Arabic RTL direction handling |
| Database | PostgreSQL 18, maintained minor release | Relational records, decimal values, constraints and stock/financial transactions |
| Login | Laravel starter authentication with server sessions | Staff accounts; disable public self-registration and create users through authorized administration |
| Background processing | Laravel database-backed queue and scheduler | Fiscal retries and necessary scheduled work; run a worker alongside the web app |
| Financial arithmetic | One PHP calculation module using BCMath; PostgreSQL numeric columns | Exact decimal operations with explicit precision/rounding; no PHP/JS floating-point accounting |
| Build tools | Starter's Vite setup, Composer and npm with committed lockfiles | Reproducible PHP dependencies and frontend assets |
| Checks | Laravel feature/unit tests using PHPUnit; starter lint/type/build checks | Focused backend correctness and frontend verification, expanded only where needed |

These major versions are installed in the Docker development environment; exact dependency versions are in composer.lock and package-lock.json. The initial build, type checks and Arabic RTL browser workflow are verified locally. Use maintained compatible releases and keep lockfiles current. Do not independently assemble a second frontend framework or another authentication service.

### Why this fits the store

Laravel supplies the backend conventions for the transaction-heavy accounting work. React handles barcode-driven basket interaction and reusable UI. Inertia connects those screens to Laravel routing/controllers, keeping normal browser workflows inside one application rather than requiring a separately maintained frontend API. PostgreSQL provides the relational foundation for invoices, allocations, stock and accounting records.

This choice keeps PHP for authoritative business logic and TypeScript for UI. It does introduce two languages; keep their responsibilities explicit rather than duplicating the financial engine. Compared with a separately deployed React frontend and API, it reduces setup and integration work. Compared with server-driven forms alone, React gives us a suitable base for the interactive cashier screen and later visual changes. This is a project judgment, not a claim that other stacks cannot work.

Use React's local state for the basket and Inertia's form/navigation facilities for normal workflows. Do not add a global state library or a second query layer until a concrete interaction requires it. Controllers validate/authorize and call focused Laravel actions; Eloquent handles ordinary persistence. Do not wrap every model in a generic repository or make business logic depend on page components.

The browser may show provisional values, but Laravel owns tax, rounding and posting. Request authoritative totals before payment confirmation. Reuse the server calculation module for previews, save and corrections; do not write a second tax/accounting engine in TypeScript. Send decimal values across the interface without silently converting them into binary floating-point amounts.

### Deployment and integrations

Initial deployment shape: one PHP web application, a queue worker and scheduler, managed PostgreSQL, and private persistent evidence/artifact storage through Laravel's filesystem abstraction. Prefer managed operations and verified backups. Hosting vendor, region, capacity and storage backend are not selected or purchased in this decision. Choose them with actual availability, budget and applicable storage requirements; do not create a complex hosting architecture in advance. Node is needed for asset builds, not a separate frontend runtime when Inertia SSR is disabled. SSR is unnecessary for the signed-in MVP.

Barcode input remains browser/device work. Start with receipt printing through the device's supported driver/browser path; use a local print bridge only if real testing establishes the need for silent printing or device commands. Do not choose a printer SDK or promise Bixolon compatibility from its brand alone.

Keep ZATCA processing behind a focused Laravel integration boundary using the application's immutable invoice data. The framework does not provide ZATCA compliance. The integration component/provider and signing/onboarding details still need verification; select an established compatible option where it reduces implementation work. Avoid a separate invoicing application with a second financial ledger.

### Sources checked

- [Laravel 13 release/support policy](https://laravel.com/framework/docs/13.x/releases): PHP 8.4 is supported; Laravel 13 has security support through 17 March 2028.
- [Official React starter](https://laravel.com/framework/docs/13.x/starter-kits#react): React 19, TypeScript, Inertia 3, Tailwind 4, shadcn/ui and Laravel authentication.
- [Inertia](https://inertiajs.com/): React screens with server-side routing.
- [shadcn/ui RTL guide](https://ui.shadcn.com/docs/rtl): direction support and component-specific migration limitations.
- [PostgreSQL support policy](https://www.postgresql.org/support/versioning/): PostgreSQL 18 is supported through 14 November 2030; use maintained minor releases.
- [ZATCA XML implementation standard](https://zatca.gov.sa/ar/E-Invoicing/SystemsDevelopers/Documents/20230519_ZATCA_Electronic_Invoice_XML_Implementation_Standard_%20vF.pdf), version 1.2 currently linked from the [official specifications page](https://zatca.gov.sa/en/E-Invoicing/SystemsDevelopers/Pages/E-Invoice-specifications.aspx), checked 5 October 2026: seller identity/address and format rules BR-KSA-09, 37, 40 and 66 informed the company form. Format validation does not verify the VAT number or establish fiscal compliance.
- [Laravel queues](https://laravel.com/framework/docs/13.x/queues) and [PHP BCMath](https://www.php.net/manual/en/book.bc.php): background jobs and decimal arithmetic primitives. Rounding rules still belong to this application.

## Implemented foundation

- Standard Laravel routes/controllers, form requests and Eloquent models handle staff sessions, catalogue, stock openings, previews and sale history. Owner/accountant writes and sale-read permissions are enforced on the server.
- `app/Domain/Sales/BasketCalculator.php` is the shared preview/save entry point; `InclusiveLineCalculator.php` owns decimal arithmetic. The current rounding policy is in README. A quote fingerprint forces price/tax/snapshot review when catalogue data changes. `PaymentCalculator.php` owns cash/card allocation and change; the browser requests those results rather than duplicating arithmetic.
- `RecordSale.php` owns the atomic workflow: locks station, exact owned open shift, ordered products and balances; checks availability; records snapshots, applied payments and quantity movements; calls `JournalPoster.php` for a balanced revenue/VAT journal. The unique operation UUID plus canonical request hash makes unchanged retries return the original sale and rejects changed payloads. Production sale posting is disabled while fiscal issuance is missing.
- `app/Domain/Stock/RecordOpeningStock.php` records one counted quantity per product with provenance. `StockAvailability.php` is shared by payment review and save. The [cost method remains undecided](store-mvp-workflows.md#data-relationships-and-posting-outcomes); this slice has no valuation or cost posting.
- `resources/js/hooks/use-server-preview.ts` provides cancellation, stale-result isolation and retry for basket/payment previews. `use-recorded-submission.ts` shares persisted attempt/recovery handling between sale checkout and shift opening. `use-sale-checkout.ts` retains the existing sale storage key and accepts legacy saved requests. Basket removal is persisted before that identity is cleared.
- Shared fields, money/date displays and pagination reuse a small set of shadcn primitives. `resources/css/app.css` is the semantic color/theme-token layer. Arabic UI is written directly.
- Staff creation uses an interactive CLI with role selection; public registration is disabled. Browser drafts are scoped by user/station. Stations identify logical cashier workspaces; shifts reserve one station per cashier but do not bind a physical device.
- `app/Domain/Shifts` owns opening, owned-open checks, cash/card totals and closing. Opening locks station then cashier; sale/close lock station then shift. PostgreSQL partial unique indexes prevent overlapping active station/cashier assignments. Closing captures immutable totals under that lock. Counted opening cash is not posted as income or a transfer. Product behavior and future cash movements are maintained in [Cashier close and returns](store-mvp-workflows.md#cashier-close-and-returns).
- Sale request identity includes the exact shift ID for new attempts, preventing a stale attempt from moving to a replacement shift. Earlier committed requests without a shift retain their original canonical hash and can replay; new requests without an owned open shift are rejected. Historical sale-to-shift links remain null. Shared `SalesTable` renders central and per-shift histories.
- `CompanyProfile` stores the single store's validated seller identity/address; only the owner manages it. `RecordSale` captures a JSON seller snapshot at posting. Missing historical seller details remain null. `SalePolicy` owns record/receipt read authorization. The receipt route only reads existing snapshots and uses the shared `SaleReceipt`, `Money` and `DateTime` components; screen controls are hidden for print. Physical paper widths are named CSS tokens, not a printer SDK or a compliance claim.
- The database-backed queue is configured but no fiscal jobs are implemented. Development Compose does not run a production server, queue worker or scheduler.

Continue implementing only the boundaries needed by the next complete workflow.

## Build a base that can grow

Start with one modular application, one backend and one relational database. A module is a clear responsibility inside the application, not a separately deployed service. Keep database migrations and explicit module entry points so features can evolve in small changes.

Support the actual opening workload: four simultaneous cashier stations. Use database transactions, constraints and sensible queries. Do not claim future throughput without measurement. Add indexes for actual lookup/report queries, bound and paginate lists, and avoid fetching the full catalogue into every POS session.

Use the selected stack's established routing, authentication, forms and data access conventions. Add dependencies for a demonstrated task; prefer a maintained existing primitive over recreating it. Record meaningful changes in the decision table here. Reconsider the stack only for a concrete incompatibility or a new owner decision.

Do not introduce microservices, a plugin platform, speculative SaaS tenancy, a generic workflow engine, a universal CRUD framework, an event bus or caching infrastructure for hypothetical growth. Add them only when a measured or concrete requirement makes the cost worthwhile.

## Proposed responsibility map

Create these boundaries as their workflows are implemented, following Laravel and the official React starter's file conventions. Do not scaffold empty modules or create an elaborate custom folder system.

| Responsibility | Owns | Reuses |
| --- | --- | --- |
| Identity and sessions | Users, permissions, POS station and cashier session | Shared validation/error conventions |
| Catalogue | Products, selling units, barcodes, current prices and tax classification | Shared field and picker components |
| Sales | Baskets, sales, returns, sale history | Catalogue, money/tax rules, stock, payments, accounting, fiscal issuance |
| Purchasing | Supplier bills, receipt of goods and supplier returns | Contacts, money/tax rules, stock, payments, accounting |
| Money and expenses | Payments/allocations, expenses, transfers, advances and settlements | Contacts, money/tax rules, accounting |
| Stock | Quantity movements, availability, costing and adjustments | Source-document identities and unit conversions |
| Accounting | Journal entries and account balance rules | Authoritative document and payment values |
| Fiscal and printing | Official artifacts, external integration, print/reprint outcomes | Issued document snapshots |
| Reports | Read-only projections with drill-down to source | The authoritative records, never a second editable ledger |

A workflow coordinates these responsibilities inside one database transaction where applicable. Other modules use its entry points rather than privately rewriting its rules. Keep coordination explicit and short; do not build a framework for orchestration.

Share line-total, tax, discount and rounding calculations through the authoritative PHP calculation module defined in the stack decision. Document required external rounding differences explicitly rather than introducing accidental competing formulas.

Keep related concepts distinct: a supplier bill and its payment, a stock receipt and a cash receipt, a sale and its print attempt, an employee advance and its spending. Reuse primitives and services while keeping their business workflows explicit.

## Reusable components without a component framework

Build components when their first workflow needs them. Start with proven primitives and a few named variants. Reuse existing APIs; do not maintain separate copies for each page.

| Component family | Shared responsibility |
| --- | --- |
| Buttons, fields and dialogs | Consistent interaction, labels, disabled/focus states and validation |
| Money, quantity and status displays | Consistent formatting and readable state labels |
| Contact and product pickers | Search, selection and clear no-result feedback |
| List/table patterns | Bounded data, filters, loading/empty/error states and pagination |
| Document header and line controls | Common fields and line interaction where semantics match |
| Payment rows | Method/amount interaction; workflow-specific checks stay in the owning module |
| Evidence attachments | Attach/view evidence and identify the source document |

Do not make sales, purchases and expenses share a single form with many mode flags. A POS can have a specialized screen while sharing fields, totals, pickers and payment primitives. Do not share mutable basket state across stations or across a POS and another form.

Extract a repeated responsibility when it has matching behavior or a concrete second consumer. Do not create abstract factories, inheritance hierarchies or wrappers around every library function. A small amount of distinct workflow code is preferable to a misleading abstraction.

## One theme-token layer

Define a small set of semantic tokens: page/surface backgrounds, text/muted text, border, primary action, success, warning, danger, focus; plus spacing, font scale, radii and control sizes. Components consume these tokens and named variants. Screen layout can differ, but raw color/spacing choices must not be copied throughout pages.

Use one readable Arabic font stack and one default theme. Token changes must update shared components without business-logic edits. Do not build a theme editor, dark mode, branding system, Figma library or animation package now. Add only styling needed for readable, accessible interaction.

## Arabic RTL as the default

The product is Arabic and RTL from the start. Set the document language/direction and use logical start/end properties for layout, alignment, spacing and borders. Write Arabic UI text directly near its owning component or feature. Reuse shared component labels where appropriate. Do not add translation infrastructure, parallel English content or a language switch.

Keep technical identifiers and supplied data intact. Barcodes, phone numbers and some references need isolated LTR presentation. Direction handling must not alter the underlying value, keyboard entry or scan result. Numeric display and input conventions must be consistent; accept the input formats chosen for the store and normalize deliberately. Do not reverse all icons or numeric columns blindly.

Ordinary product forms need an Arabic name; do not require a second English name because Technolink has that field. Preserve imported descriptions and add other fields only when an actual workflow or official format requires them. A fiscal adapter's required format does not justify a bilingual product UI.

## UX acceptance for each slice

- Keep the primary task and totals visible. Use concise Arabic action labels and errors that say how to recover.
- POS entry must work with the actual scanner, keyboard and touch screen. Keep the scan target predictable; do not steal focus from payment or search. After closing a dialog, restore focus to the appropriate control.
- Prefer few fields and sensible defaults. Reveal optional details when needed. Do not ask a cashier to fill accounting classifications.
- Preserve input and baskets after recoverable errors. Prevent accidental double submission and allow safe recovery after an uncertain response.
- Show loading, empty and error states in the same shared patterns. Distinguish a confirmed sale from pending fiscal processing and a failed print. Provide lookup/reprint rather than another checkout.
- Keep related records traceable: from a balance to its documents, from a document to its payments and stock effects.
- Use visible focus, labels and sufficient contrast. Do not rely on color alone. Confirmations belong at actions with a meaningful consequence, not every routine step.
- Perform a focused RTL/keyboard review for affected screens. Test the real four-station workflow before release; do not demand polished visual design for a functional slice.

## Data and integration foundations

Use stable IDs and foreign keys, explicit decimal money representation, issued-document snapshots and source references. Store event timestamps consistently in UTC and display them in Asia/Riyadh. Use a date value for a date-only source document; avoid silently converting historical dates. Select numeric precision from actual unit/price/tax requirements when defining each migration.

Use database migrations and constraints for uniqueness, allocation limits and concurrency-sensitive changes. Financial posting uses one transaction. Read-side summaries may be computed or cached when justified, but must be reproducible from source records; an editable dashboard total is not an accounting source.

Printer and fiscal calls are external side effects. Introduce small adapters where integration is real, keeping vendor details out of page components. Use durable processing state and retries when needed; a database-backed job is sufficient initially unless requirements justify another service. Keep operation identities stable so retries do not duplicate business records.

Expose useful failure context without secrets or private invoice contents in ordinary logs. Back up the database and attachments, and demonstrate recovery before production. Add performance monitoring or heavier infrastructure only for a demonstrated operational need.

## Focused verification and growth

Use the risk-based checks in [AGENTS.md](../AGENTS.md). Prefer a small set of meaningful calculation/transaction tests and focused workflow checks over full-page snapshots or a fixed coverage target. Manual review is enough for ordinary documentation or styling changes. Keep fiscal/hardware/restore acceptance separate from unit tests.

For a new feature: identify its owning module, reuse the existing primitives/rules, add only required schema changes, and validate the affected workflow. Change shared code once and check its actual consumers. If a boundary no longer fits a concrete feature, adjust it locally instead of predicting the next ten features.

## Decision record

| Decision | Status / basis |
| --- | --- |
| Arabic RTL, direct Arabic UI text | Confirmed by owner; no translation work in MVP |
| Shared tokens and components; plain initial appearance | Confirmed by owner; support later visual changes |
| Small slices and focused verification | Confirmed by owner; protect high-impact behavior without test bloat |
| Laravel 13 + React/TypeScript/Inertia + PostgreSQL 18 | Selected 5 October 2026 under owner's delegated stack choice; one modular application |
| Official React starter with Tailwind/shadcn | Selected to reuse existing foundations; Arabic RTL still requires focused verification |
| Server-side financial module and database-backed jobs | Selected to centralize rules and avoid separate infrastructure initially |
| Module/component map | Proposed boundaries; implement only as needed |

Update this table for meaningful architectural choices. Do not create a separate decision document for routine component or file choices.
