# AGENTS.md – Wilson Stock Order Plugin

You are working on a custom WordPress + WooCommerce plugin used ONLY on the
parent multisite `wilson-organisation.com`. It is an internal ERP-style stock
ordering system for Wilson-Organisation Ltd.   

The plugin’s job is to:
- Forecast demand per SKU (typically over 6-month cycles).
- Plan supplier purchase orders with lead times and China holidays.
- Track CBM and container capacity.
- Generate supplier order sheets (CSV/XLSX).
- Later: help with goods-in / container receiving.

The codebase you see is already a **partially built plugin**, currently around
Phase 4.1 (pre-order sheet UI). You must **extend and refine** it, not rewrite
from scratch.

---

## Repo & runtime

- Plugin folder: `stock-order-plugin/`
- Key files and folders:
  - `stock-order-plugin.php` — main plugin bootstrap and hooks.
  - `/includes` — core logic (data access, forecasting, calculations, helpers).
  - `/admin` — admin-only UI (settings pages, pre-order sheet, list tables).
  - `/assets` — admin JS/CSS (if present).
- PHP: 8.1+.
- Platform: WordPress multisite, WooCommerce store on parent site.
- No front-end output; everything is WordPress admin-side.

Follow the user’s **Master Project Instructions** in this ChatGPT project:
- GitHub is the single source of truth.
- Every PHP file has a header with phase + description + file version.
- Increment file version numbers on each edit.
- Maintain backwards compatibility unless explicitly told otherwise.

---

## Naming, prefixes & style

- All plugin identifiers must use the `sop_` prefix:
  - Functions, classes, hooks, DB tables, options, meta keys.
- Coding style:
  - WordPress coding standards.
  - No external APIs or SaaS calls.
  - Use WooCommerce APIs where possible (orders, products, stock).
- Public classes and key functions must have docblocks.

---

## Critical business data (MUST NOT overwrite)

The existing WooCommerce/meta fields are *authoritative* and must be read, not
replaced:   

- SKU.
- Product name.
- Stock quantity.
- Dimensions (length, width, height) in cm.
- Weight.
- Cost price (GBP) – WooCommerce native cost field.
- Supplier cost price (e.g. RMB) stored at product level.
- Supplier association meta (e.g. supplier ID per product).
- Product brand taxonomy and categories.
- `max_order_qty_per_month` — **critical manual ceiling**.
  - Never overwrite this meta.
  - Never “recalculate” and write a new value into it.
  - If you need plugin-side limits, store them separately.

Discontinued items are simply products with **no supplier assigned**; they must
be automatically excluded from all forecasts and order proposals.

---

## Plugin data you *can* own

The plugin may create and maintain its own data:

- Custom DB tables for:
  - Stockout logs.
  - Forecast snapshots/cache.
  - Suggested order quantities per supplier & run.
- Plugin options (e.g. default look-back period, buffer rules).
- Supplier configuration:
  - Lead time, currency, container types/capacities, column layouts.
- Goods-in / receiving logs (later phase).
- Any “plugin suggested max order” values (separate from
  `max_order_qty_per_month`).

All tables and options must be safely created/updated on activation with
idempotent migrations.

---

## Current implementation status (approx.)

The v1.5.5 plugin already contains early implementations of: :contentReference[oaicite:3]{index=3}  

- A main admin menu for the Stock Order Plugin.
- Per-product meta box for supplier and SOP meta fields.
- Supplier-filtered “pre-order sheet” UI:
  - Shows products for a selected supplier.
  - Supports basic CBM calculations using product dimensions.
  - Handles multiple supplier currencies (RMB, GBP and others).
  - Editable fields for min order qty and manual order qty via SOP meta.
- A CBM bar / container fill indicator.
- Core engine scaffolding for forecasting and ordering.

When editing code, **respect existing structures**:
- Don’t rename SOP meta keys without a clear migration.
- Don’t rip out the pre-order sheet; extend it to fit the Blueprint.

---

## Business logic snapshot (what the system must model)

Use the Blueprint documents in `/docs` as the detailed specification. Core
rules include:   

1. **Forecasting**
   - Default 12-month lookback, configurable.
   - Detect stockouts (stock transitions to 0 and back) and exclude those days
     from “days on sale”.
   - Support optional weighting so recent months count more.
   - For each SKU:
     - Work out demand over lead time + buffer (e.g. 6-month cycle).
     - Respect `max_order_qty_per_month` as a ceiling/reference only.
     - Subtract projected stock on arrival date.
     - Produce a suggested order quantity (possibly 0).

2. **Supplier context**
   - Everything runs supplier-by-supplier.
   - Each supplier has:
     - Currency.
     - Lead time.
     - China holiday delays (where applicable).
     - Container options and capacities.
     - Export column layout.

3. **Containers & CBM**
   - Per-unit CBM from L×W×H in cm (divide by 1,000,000).
   - For any proposed order:
     - Line CBM = qty × per-unit CBM.
     - Container fill % vs chosen container type.
     - Colour/alert ranges for underfilled / optimal / full / overfull.

4. **Pre-order sheet UX**
   - Primary working screen for the user.
   - Columns: image, SKU, name, brand, category, stock, forecast, suggested qty,
     editable order qty, CBM per line, cost, etc.
   - Sorting, filtering, and rounding controls (round to 1/5/10, mark rounded).

5. **Goods-in (later phase)**
   - Import supplier’s confirmed sheet (carton numbers + final quantities).
   - Receiving screen for ticking off and adjusting items.
   - Bulk stock_delta updates in WooCommerce.
   - Reports for shortages/damage/substitutions.

---

## Phased development

You must respect the phased approach. Each phase should be a coherent unit that
doesn’t break earlier phases:   

1. **Phase 1 – Core forecasting & suggested order report**
   - Forecast engine, stockouts going forward, per-supplier suggested quantities.

2. **Phase 2 – Supplier settings & export sheet**
   - Supplier admin UI, export layouts, CSV/XLSX generation.

3. **Phase 3 – Container CBM & capacity**
   - Robust CBM calculations and container fill UI.

4. **Phase 4 – Advanced analytics & refinements**
   - Top sellers, low volume, overstock flags, comparison to
     `max_order_qty_per_month`, UX refinements.

5. **Phase 5 – Goods-in / container receiving**
   - Optional module for receiving / stock_delta bulk updates.

v1.5.5 is partially through Phases 1–3 and especially Phase 4.1 (pre-order
sheet). Do **not** re-implement these blindly; extend them.

---

## Testing & commands

- Where Composer/PHPCS/PHPUnit are present:
  - Run tests and linters before completing a large task.
- If there is no existing test suite:
  - Propose minimal tests for core calculation functions.
- Never leave syntax errors:
  - Ensure each modified PHP file passes `php -l`.

---

## Behaviour for Codex

When a task is requested:

1. Read `AGENTS.md` and `docs/requirements-stock-order-plugin.md` first.
2. Inspect existing plugin files instead of starting from scratch.
3. Propose a short plan in the diff or summary.
4. Make focused, minimal changes aligned to the phase.
5. Keep file headers and version tags in sync with the Master Project
   Instructions.
