# Agent instructions

Build a practical Arabic RTL POS and accounting platform for this store. Favor clear workflows, reusable foundations and small complete changes. Follow the user's latest scope; a documentation-only request means no application code, scaffolding or dependency installation.

## Read only what the task needs

- Start with [README](README.md) for project status and the document map.
- For product behavior, read the relevant section of [MVP workflows](docs/store-mvp-workflows.md).
- Before implementation or structural changes, read [Engineering guide](docs/engineering-guide.md).
- Use [Reference review](docs/reference-system-review.md) when evidence about Technolink is needed. Observed reference features are not automatically requirements.
- Read applicable directory instructions and inspect existing code before editing. The selected stack is defined in the engineering guide; follow it instead of reopening the choice for routine tasks. The first application slice exists; checked setup commands and current feature boundaries are in README. Do not claim later workflows are implemented.
- The latest user decision takes precedence over older project notes. Update affected documents when a decision changes; ask only about contradictions that affect the current task.

## Work in small slices

1. Identify the user outcome and existing components, helpers and module entry points that can support it.
2. Explain the scope briefly before broad changes, data migrations or adding dependencies. Choose routine implementation details without repeated approval requests.
3. Complete one coherent workflow or fix. Avoid unrelated cleanup, large rewrites and speculative features.
4. Validate the affected behavior with the smallest useful check. Broaden checks only for new failures or unresolved risks.
5. Report what changed, exact commands if applicable, checks actually run and remaining limitations. Never claim a feature or test passed without evidence.

Keep progress and final replies concise. Use focused searches and bounded file reads. Do not reread the full repository, repeat successful checks, create redundant planning documents or use multiple agents unless requested.

## Git checkpoints and autonomous continuation

The owner authorized direct work in the existing `main` checkout on 5 October 2026. Do not create branches, worktrees or pull requests for routine slices. Treat each complete feature as a checkpoint: review the change, run the relevant checks, update affected existing documentation, commit and push `main`, then continue to the next useful slice without waiting for approval. Fetch and preserve remote changes; never force-push or discard unrelated work.

Before implementing a related workflow, consult Technolink and the temporary accounting spreadsheet through the source links in [MVP workflows](docs/store-mvp-workflows.md#what-the-current-work-actually-involves). Keep both sources read-only. Use existing reference evidence when access is unavailable, mark any remaining uncertainty and continue independent work. Do not repeatedly inspect unrelated screens or copy private source records into the repository.

Autonomous continuation does not resolve the undecided costing method, fiscal onboarding, unknown opening balances or hardware details. Do not invent those inputs, enable production checkout or deploy externally. Complete work supported by the agreed requirements and report concrete blockers with the next checkpoint summary.

## Reuse and module boundaries

- Reuse existing components and extend their small, explicit APIs before creating alternatives. Shared buttons, fields, dialogs, money displays and status patterns use the same theme tokens.
- Give each business rule one authoritative implementation. Tax/rounding, payment allocation, stock movements and accounting posting must not be copied into pages or reports.
- Keep business logic separate from UI, transport and database plumbing. Use ordinary functions and focused modules; do not create a generic framework to enforce this separation.
- Share code when behavior is actually shared. Do not force sales, supplier bills and expenses into one universal form or posting function with many flags.
- When repetition reveals the same responsibility, extract it and update the affected callers in the same slice. Similar appearance alone is not evidence of identical business behavior.
- Add module-specific code inside its module. Keep shared code small and named by responsibility; avoid dumping unrelated code into a global utilities file.

## Arabic, RTL and UX

- Product UI is Arabic and RTL. Write user-facing Arabic directly; no English UI, translation pipeline, bilingual fields or language switch unless required later. Technical identifiers can be English; preserve supplied source data and legally required fields.
- Use logical start/end layout and spacing. Isolate barcodes, technical references and phone numbers where LTR is needed. Never change a scanned value to make it look Arabic.
- Use semantic theme tokens and reusable component variants for color, spacing, typography, borders and states. Do not scatter raw styling values across screens.
- Keep the initial appearance plain and usable. No branding exploration, decorative animation, multiple themes or design-system showcase.
- Make scan focus, keyboard/touch access, readable totals, clear Arabic validation, loading/empty/error states and recovery part of the workflow.
- Preserve a cashier's basket or form after recoverable errors. Distinguish saving, confirmed sale, fiscal processing and print failure; never imply success before confirmation.

## Protect money and records

- Authorize actions and validate business rules on the server. Client checks improve UX but do not establish permission or totals.
- Use decimal or explicit integer money arithmetic with one rounding policy. Posted values and document snapshots must be traceable.
- Commit related database records atomically. Prevent duplicate posting and enforce shared-stock consistency across four stations.
- Handle fiscal and printer side effects through explicit outcomes and appropriate retry handling. A reprint or retry must not create another sale or payment.
- Keep issued documents immutable; use linked corrections. Supplier payments, transfers and employee settlements must not duplicate purchases or expenses.
- Use reviewed migrations, preserve import provenance and unknown historical values, and keep credentials and private records out of fixtures/logs.
- Official invoicing is required. Use current ZATCA specifications; VAT registration is confirmed by the owner, while the company's Phase 2 deadline/details remain unverified. Continue independent work without inventing compliance or hardware compatibility.

## Verification proportional to risk

- For documentation, labels, spacing and ordinary component reuse, use a focused review or UI check. Do not add tests that merely restate the implementation or snapshot every screen.
- For money/tax calculations, posting, permissions, concurrency, returns, migrations and integration retries, use focused automated tests for meaningful outcomes. Include regression coverage for an actual bug when worthwhile.
- Run relevant existing checks when code changes. Once passed, stop repeating them without a reason. Do not chase an arbitrary coverage target or build test infrastructure before a real need.
- Hardware, fiscal integration and backup restoration need practical validation before production. Automated tests alone do not establish readiness.

## Keep documentation maintainable

Each decision has one authoritative home. Link to it instead of copying it. Mark observations, agreed requirements, proposals and unresolved inputs accurately. Update only the documents affected by the change; avoid writing a report for every routine edit.
