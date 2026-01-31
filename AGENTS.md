# AGENTS.md â€“ Wilson Stock Order Plugin

You are working on a custom WordPress + WooCommerce plugin used ONLY on the
parent multisite `wilson-organisation.com`. It is an internal ERP-style stock
ordering system for Wilson-Organisation Ltd.

---

## Execution and response rules

- Execute requested edits immediately; do not reply with plan-only responses.
- Do not paste full file contents unless explicitly requested.
- If blocked, reply with "BLOCKED:" and the exact missing file/anchor.
- Finish notes must include: summary, files changed, static checks performed, commit message suggestion.

---

## Output policy (chat bloat control)

- DEFAULT RESPONSE FORMAT (for every task):
  1) Summary (2â€“6 bullets)
  2) Files changed (exact paths)
  3) Static checks performed (explicit checklist)
  4) Commit message suggestion (single line)

- STRICT RESPONSE LENGTH (mandatory)
  - Default: Max 10 lines TOTAL per response (including blanks).
  - No internal reasoning, no step-by-step logs, no long diagnostics.
  - Allowed format within 10 lines:
    1) Summary (2â€“4 lines max)
    2) Files changed (1 line)
    3) Static checks performed (2â€“3 lines max)
    4) Commit message suggestion (1 line, MUST be the final line)
  - Exception: Only exceed 10 lines if the user explicitly requests verbose output/debug logs.

- DO NOT paste full file contents by default.
  - Never paste entire PHP files, JS files, or long templates into chat.
  - Never say â€œpreparing full updated file outputâ€ unless the user requested full file output.

- ONLY paste complete file contents when the user explicitly requests it using wording like:
  - â€œpaste the full fileâ€
  - â€œoutput the complete fileâ€
  - â€œgive me the entire file contentâ€
  Otherwise: do not.

- For review, direct the user to rely on:
  - VS Code diff view (Source Control / inline diff)
  - or provide a short, focused snippet ONLY if itâ€™s under ~50 lines and only when it clarifies a change.

---

## Agent Output Rules (Mandatory)

1) After completing any edit task, the agent response MUST contain ONLY:
   - Up to 10 lines total summarising what changed (plain text)
   - One final line: "Commit message: â€¦"
   No other sections, no headings, no code blocks, no diffs, no file contents.

2) NEVER paste PHP/JS/CSS or any large code output into chat. All code changes must be made directly in the repository files in VS Code.

3) NEVER claim you ran any CLI commands (git, php -l, wp-cli, tests, etc.). You cannot execute commands here.
   - If asked about commit status, reply: "No commit created here; please commit via VS Code Source Control / GitHub workflow. Commit message: â€¦"
   - Do not mention `git status` or show imaginary outputs.

4) If Gary explicitly asks to see a snippet, keep it to <= 10 lines and only the specific snippet requested.

5) Keep text plain (no unusual formatting, no character-by-character output, no markdown that turns underscores into italics).
6) NEVER introduce UTF-8 BOM. PHP files must start with "<?php" as the very first bytes (no BOM/whitespace) and omit the closing "?>".
7) After editing any PHP file, explicitly confirm in static checks: "No BOM added" and "No closing PHP tag".


## Chat Output Policy (MANDATORY)

- DO NOT paste full file contents into chat unless the user explicitly asks for a full file to be pasted.
- Default completion response MUST be:
  1) Summary: max 10 lines total (include static checks in ONE line inside those 10 lines)
  2) One final line: "Commit message: <message>"
- Include ONE line within the 10-line summary stating:
  "I will not paste full file contents here because the repository/VS Code is the source of truth and to avoid noise/risk."
- If the user asks for code, point them to VS Code file paths first; only paste code if explicitly requested.The pluginâ€™s job is to:
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
  - `stock-order-plugin.php` â€” main plugin bootstrap and hooks.
  - `/includes` â€” core logic (data access, forecasting, calculations, helpers).
  - `/admin` â€” admin-only UI (settings pages, pre-order sheet, list tables).
  - `/assets` â€” admin JS/CSS (if present).
- PHP: 8.1+.
- Platform: WordPress multisite, WooCommerce store on parent site.
- No front-end output; everything is WordPress admin-side.

Follow the userâ€™s **Master Project Instructions** in this ChatGPT project:
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
- Cost price (GBP) â€“ WooCommerce native cost field.
- Supplier cost price (e.g. RMB) stored at product level.
- Supplier association meta (e.g. supplier ID per product).
- Product brand taxonomy and categories.
- `max_order_qty_per_month` â€” **critical manual ceiling**.
  - Never overwrite this meta.
  - Never â€œrecalculateâ€ and write a new value into it.
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
- Any â€œplugin suggested max orderâ€ values (separate from
  `max_order_qty_per_month`).

All tables and options must be safely created/updated on activation with
idempotent migrations.

---

## Current implementation status (approx.)

The v1.5.5 plugin already contains early implementations of:

- A main admin menu for the Stock Order Plugin.
- Per-product meta box for supplier and SOP meta fields.
- Supplier-filtered â€œpre-order sheetâ€ UI:
  - Shows products for a selected supplier.
  - Supports basic CBM calculations using product dimensions.
  - Handles multiple supplier currencies (RMB, GBP and others).
  - Editable fields for min order qty and manual order qty via SOP meta.
- A CBM bar / container fill indicator.
- Core engine scaffolding for forecasting and ordering.

When editing code, **respect existing structures**:
- Donâ€™t rename SOP meta keys without a clear migration.
- Donâ€™t rip out the pre-order sheet; extend it to fit the Blueprint.

---

## Business logic snapshot (what the system must model)

Use the Blueprint documents in `/docs` as the detailed specification. Core
rules include:

1. **Forecasting**
   - Default 12-month lookback, configurable.
   - Detect stockouts (stock transitions to 0 and back) and exclude those days
     from â€œdays on saleâ€.
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
   - Per-unit CBM from LÃ—WÃ—H in cm (divide by 1,000,000).
   - For any proposed order:
     - Line CBM = qty Ã— per-unit CBM.
     - Container fill % vs chosen container type.
     - Colour/alert ranges for underfilled / optimal / full / overfull.

4. **Pre-order sheet UX**
   - Primary working screen for the user.
   - Columns: image, SKU, name, brand, category, stock, forecast, suggested qty,
     editable order qty, CBM per line, cost, etc.
   - Sorting, filtering, and rounding controls (round to 1/5/10, mark rounded).

5. **Goods-in (later phase)**
   - Import supplierâ€™s confirmed sheet (carton numbers + final quantities).
   - Receiving screen for ticking off and adjusting items.
   - Bulk stock_delta updates in WooCommerce.
   - Reports for shortages/damage/substitutions.

---

## Phased development

You must respect the phased approach. Each phase should be a coherent unit that
doesnâ€™t break earlier phases:

1. **Phase 1 â€“ Core forecasting & suggested order report**
   - Forecast engine, stockouts going forward, per-supplier suggested quantities.

2. **Phase 2 â€“ Supplier settings & export sheet**
   - Supplier admin UI, export layouts, CSV/XLSX generation.

3. **Phase 3 â€“ Container CBM & capacity**
   - Robust CBM calculations and container fill UI.

4. **Phase 4 â€“ Advanced analytics & refinements**
   - Top sellers, low volume, overstock flags, comparison to
     `max_order_qty_per_month`, UX refinements.

5. **Phase 5 â€“ Goods-in / container receiving**
   - Optional module for receiving / stock_delta bulk updates.

v1.5.5 is partially through Phases 1â€“3 and especially Phase 4.1 (pre-order
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

---

## Codex Execution Rules (VS Code Integration Layer)

You are operating inside Visual Studio Code as the **Stock Order Plugin Development Agent**.
When receiving a task from the user, you must follow the rules below precisely:

### 1. Always read first

Before writing or editing any file, always re-read:

- `AGENTS.md`
- `docs/requirements-stock-order-plugin.md`
- The relevant plugin files involved in the task

Do not rely on memory. Always inspect the repository state.

---

### 2. Editing behaviour

All edits must follow these standards:

- Always output complete, valid files when modifying PHP.
- Never output partial diffs unless the user explicitly requests partial patches.
- Maintain or increment file version headers exactly as defined.
- Maintain all prefixes (`sop_`), class names, meta keys, and hooks.
- Do not create new files unless the user explicitly authorises it.
- Never delete existing functions or structures unless instructed.

---

### 3. Safety checks

Before applying changes:

- Validate the full file structure (class braces, function braces, PHP open tags).
- Ensure no duplicate function names.
- Ensure proper file paths.
- Ensure compliance with WordPress/WooCommerce conventions.
- Run an internal syntax check equivalent to `php -l`.

If you detect a risk, warn the user and propose a safer adjustment.

---

### 4. Phase workflow compliance

When a task affects multiple files or is part of a phase:

- Produce a short, clear plan before editing.
- Group multi-file actions logically.
- Ask for A/B/C/D confirmation if the task is large or risky.
- Reference which Phase (1â€“5) the task belongs to.

---

### 5. Minimal-touch development

- Make the smallest safe change required to implement the task.
- Do not refactor unrelated code.
- Do not redesign UI/UX without explicit instruction.
- Respect prior phases and maintain backwards compatibility.

---

### 6. Output format

All responses must be in Codex-ready form, meaning:

- Clear, actionable instructions.
- Complete file rewrites (PHP) when editing.
- File path included for each edit.
- No placeholders, no partial code.
- No markdown fencing around PHP files.

---

### 7. After writing

Before finalising changes:

- Re-read the updated file.
- Revalidate syntax.
- Ensure the update follows every rule in this `AGENTS.md`.
- Only then apply or output the file.


