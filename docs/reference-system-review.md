# Accounting system reference review

Reviewed: 2026-10-05 (Asia/Riyadh)

Status: reference discovery evidence. Current implementation status is maintained in [README](../README.md).

This document records what was visible in Technolink on the review date. Product scope belongs in [MVP workflows](store-mvp-workflows.md); implementation guidance belongs in [Engineering guide](engineering-guide.md). Features listed here are evidence, not a requirement to copy every feature.

## Evidence and limits

Source: [Technolink home](https://0563638246.technolinkapp.com/Home), opened in the user's signed-in Chrome session.

The review expanded all visible top-level menus, including report submenus, and inspected representative forms and lists. It did not submit invoices, payments, stock corrections, transfers, or settings. Business records, personal details, and credentials are not reproduced here.

The observed screens establish available fields and navigation. They do not establish the backend schema, posting rules, costing method, permission enforcement, or calculation accuracy. Several reports and invoice lists displayed no records in their initial view; this does not establish that historical data is absent. Features can depend on the signed-in account's permissions.

## Visible feature map

“Inspected” means the screen or form was opened. “Menu only” means the feature appeared in navigation, but its workflow was not tested.

| Area | Visible features | Review depth |
| --- | --- | --- |
| Home | Sales, sales returns, purchases, purchase returns; charts; branch orders, customer orders, expired items, service tickets | Inspected dashboard |
| Accounts / الحسابات | Accounts, money transfers, receipt and expense categories, receipts, expenses, cash drawers, drawer transactions | Inspected accounts list, transfer form, receipts list, expense form/detail, drawer list/opening form and transactions; account statement permission blocked |
| Items / الأصناف | Items, groups, item points, expiry, item printers, item notes, branch quantities, reorder items, discount offers, bundles/components, item transfers, destruction invoices, barcode, stock count | Inspected item list/form and stock count; remaining features menu only |
| Sales / المبيعات | Sales invoices, sales returns, customers, customer orders, customer messages, points history, quotations, invoice delivery | Inspected invoice form/list, return form, customer form; remaining features menu only |
| Purchases / المشتريات | Purchase invoices, purchase returns, suppliers | Inspected purchase form/list/detail, return form and supplier form |
| Services / الخدمات | Menu heading and service-ticket shortcut on home | Expanded menu showed no child entries in this session |
| Settings / الإعدادات | Company information, branches, taxes, payment methods, branch payments, system setup; setup includes answers and setting sections | Payment methods, company form, branches, answers and setting-section catalogue inspected; item-settings detail permission blocked |
| Users / المستخدمون | Permission setup, with a pages submenu | Menu only; no permission changes |
| Appointments / المواعيد | Appointments, reservations, appointment booking, my reservations | Menu only |
| Reports / التقارير | Account, sales, purchase, and item report families | All visible report submenus expanded; cash flow, tax, and sales-profit screens inspected |

### Report catalogue

- Accounts: revenues and payments, invoices, tax declaration, credit accounts, debit accounts, expenses, receipts.
- Sales: sold items, returned sold items, sales invoices, sales return invoices, sales profits.
- Purchases: purchased items, returned purchased items, purchase invoices, purchase return invoices.
- Items: destroyed items, transferred items, item costs, detailed item costs.

## Main screens and workflows

### Sales invoices

Source: [New sales invoice](https://0563638246.technolinkapp.com/sales_invoices/add) and [Sales invoice list](https://0563638246.technolinkapp.com/sales_invoices/list).

The entry screen contains invoice date, delivery status/date, wholesale flag, cash/credit/prepaid invoice type, and customer lookup. Items can be entered by code; the screen advertises a search shortcut. A touch keypad, quantity/price/unit controls, and four waiting slots support a point-of-sale style interface.

Line columns include item code, description, unit, quantity, stock, cost, price, discount, tax, profit, and total. Separate Save and Payment controls are present. The list includes invoice numbers, customer fields, totals, tax, net amount, paid amount, remaining amount, branch, creator, sending status, and a field labelled الزكاة. That last label alone does not verify an electronic-invoicing integration.

The Payment dialog includes cash/card rows, an amount per row, an add-row control, tendered amount and change. This establishes a split-payment interface, but no payment was submitted. The user confirmed this website-style POS is the required replacement, with four simultaneous devices. Hardware scanning and receipt printing have not been tested.

### Purchases

Source: [New purchase invoice](https://0563638246.technolinkapp.com/purchases_invoices/add).

The form includes supplier invoice number, date, received status/date, cash/credit/prepaid invoice type, and supplier lookup. Item rows include quantity, unit, stock, cost, discount, tax, and total. Save and Payment are separate controls.

An existing credit purchase detail showed 168 units at SAR 15, net SAR 2,520, tax SAR 378, total and outstanding SAR 2,898. The matching supplier account showed SAR 2,898. This supports a visible purchase-to-supplier-balance relationship; the underlying ledger entries remain inaccessible. The detail offered roll, A4 and custom print layouts.

### Returns

Source: [New sales return](https://0563638246.technolinkapp.com/sales_return_invoices/add).

The sales-return form includes an original-invoice reference, return date, goods received status/date, invoice type, customer, item rows, and separate Save and Payment controls. The purchase-return form includes supplier reference, date, delivery status/date, supplier, cash/credit/prepaid type, item rows and separate Save and Payment controls. No returns were submitted.

### Customers and suppliers

Sources: [Customer form](https://0563638246.technolinkapp.com/customers/add), [Supplier form](https://0563638246.technolinkapp.com/suppliers/add).

Both forms include Arabic/English names, contact details, tax identifier, account selection, and address fields. The customer form also includes an image and special-discount fields. The supplier form includes business activity and telephone fields. Mobile is marked required in both forms.

### Items and inventory

Sources: [Items](https://0563638246.technolinkapp.com/items), [Item form](https://0563638246.technolinkapp.com/items/add), [Stock count](https://0563638246.technolinkapp.com/inventory_table).

Items contain code/barcode, group, tax treatment, Arabic/English descriptions, cost, stock, reorder point, order quantity, discount limit, touch visibility, branch selection, image, and detail text. Selling units have their own code, names, conversion quantity, retail price, wholesale price, and default selection.

The stock-count screen compares counted quantity with system quantity and provides a stock-correction control. The effect of submitting a correction was not tested.

### Costing options and fiscal onboarding follow-up

Sources: [Answers](https://0563638246.technolinkapp.com/answers), [Branches](https://0563638246.technolinkapp.com/branches), [Setting sections](https://0563638246.technolinkapp.com/setting_sections), [Purchase form](https://0563638246.technolinkapp.com/purchases_invoices/add) and [Sales invoice list](https://0563638246.technolinkapp.com/sales_invoices/list). Inspected again on 5 October 2026 after the user signed in.

The answers catalogue contains `change_cost` choices **متوسط التكلفة** (`average`, average cost) and **آخر تكلفة** (`last`, last cost). These are available option definitions, not evidence of the store's selected policy. Opening the item-settings section returned Access Forbidden. The purchase entry form exposes item costs but did not reveal a selected costing policy. The average-cost formula, its update timing and the financial effect of stock corrections remain unverified.

The branch list displays a positive tick under **حالة التهيئة (onboarding)** for the store's branch. This is evidence that Technolink marks the branch as onboarded. It does not establish the production connection, successful reporting/clearance of an invoice, the company's Phase 2 deadline or readiness of the replacement platform. Branch detail actions were unavailable to this account.

The sales invoice list exposes **حالة الإرسال** and **الزكاة** columns, but displayed no invoice rows in the reviewed view. Expanded search filters showed no selected invoice type, branch or user filter. No accepted invoice, fiscal artifact or submission response was available to inspect. No live transaction or setting was changed.

### Expenses, receipts, and cash

Sources: [Expenses](https://0563638246.technolinkapp.com/expenses), [Receipts](https://0563638246.technolinkapp.com/receipts), [Accounts](https://0563638246.technolinkapp.com/accounts), [Transfers](https://0563638246.technolinkapp.com/money_transfers), [Drawer transactions](https://0563638246.technolinkapp.com/treasuries_transactions).

Expense fields include date, category, Arabic/English description, amount, tax treatment, tax amount, total, optional recipient account, source account, and payment method. Visible tax choices distinguish added VAT, tax-inclusive pricing, no tax, zero rate, exempt, and outside scope. No rate or legal treatment was validated.

The receipts list has date, description, total, category, payment, and creator columns. No Add control appeared in this session. The accounts list includes account name, parent account, balance, credit limit, and bank-account number.

The transfer form contains date, description, amount, source account, and destination account. Drawer transaction columns include date, description, debit, credit, reference number/page, and payment method.

The expense detail links category, tax treatment, payment method, user and branch. An inspected SAR 600 tax-inclusive expense showed SAR 78.26 tax, total SAR 600, company funding source and card method. The recorder is a separate field from the recipient/source.

The drawer-opening form includes date, cashier, branch, and opening amounts by cash/card method. No drawers were visible in the initial list. Closing and reconciliation behavior could not be established from existing records. Payment methods list cash, credit, prepaid, card, points and STC Pay; flag meanings and external terminal integration were not verified.

Opening a supplier statement from the account tree returned Access Forbidden for this account. This is a permission limit; no bypass was attempted.

### Reporting

Sources: [Revenues and payments](https://0563638246.technolinkapp.com/revenues_payments_rpt), [Tax declaration](https://0563638246.technolinkapp.com/tax_rpt), [Sales profits](https://0563638246.technolinkapp.com/sales_profits_rpt).

The revenues/payments screen groups account, source page, payment method, and amount. Expanded filters include account, payment method, user, and branch. The tax report has description, total, and tax columns. The profit screen has description and amount columns and an export/print control. These reports showed no rows on initial load, so their calculations remain unverified.

## Follow-up evidence still needed

Supplier account statements and item-settings details were permission blocked. Available costing options and a positive branch-onboarding indicator were observed, but the active costing formula and successful fiscal submission remain unverified. Saving/posting, hardware scanning/printing and payment execution were not tested. The initial empty lists do not establish that historical data is absent. No live records were changed.

Use the MVP workflow document for open business inputs and acceptance outcomes. Do not infer backend behavior, legal compliance or hardware compatibility from the visible fields.
