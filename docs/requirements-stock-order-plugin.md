# Stock Order Plugin – Requirements (Blueprint Summary)

This document summarises the **Full Concept Blueprint** and **Blueprint V1.1**
for the Wilson Stock Order Plugin, and aligns it with the current codebase
(v1.5.5).   

The plugin is an internal ERP-style system for Wilson-Organisation Ltd, focused
on:

- Long-term supplier ordering (typically 6-month cycles).
- Container-based planning and CBM.
- Supplier-specific lead times and rules.
- Excel/CSV order sheet generation.
- Later: goods-in and receiving.

The plugin runs only on the parent site `wilson-organisation.com`, which already
contains all orders for DBRacing, PitBikeShop, eBay and Amazon via
WooMultistore.

---

## 1. Data sources & existing meta

### 1.1 Primary data source

- WooCommerce orders and products on the parent site.
- WooMultistore already funnels all sales channels into this store.

### 1.2 Existing meta (MUST NOT be overwritten)

Read-only business-critical fields:   

- SKU
- Product name
- Stock quantity
- Dimensions (length/width/height in cm)
- Weight
- Cost price (GBP)
- Supplier cost (e.g. RMB) at product level
- Product brand taxonomy
- Product category
- Supplier association meta (e.g. supplier ID)
- `max_order_qty_per_month` – manually curated max monthly sales:
  - Calculated by the user: “if always in stock, how much can this sell in a
    month?” × 6 for a 6-month target.
  - Frequently tweaked for overstock, strong demand, etc.
  - **Never overwritten by plugin logic.**

Discontinued items:
- Simply products that have **no supplier assigned**.
- Must be automatically excluded from all forecasts and ordering.

---

## 2. Plugin-owned data & structures

The plugin can introduce its own:

- Custom DB tables, prefixed `sop_`, for:
  - Forecast cache / snapshots.
  - Stockout logs.
  - Suggested order quantities per run and supplier.
  - Optional goods-in records.
- WordPress options for:
  - Global defaults (lookback, buffer, weighting, schedule).
  - RMB→GBP or other FX rates (if needed, manually input).
- Supplier configuration:
  - Lead time.
  - Holiday delays.
  - Container types and capacities.
  - Export column layout.
- SOP product meta keys (`_sop_*`) for:
  - Supplier link (if not already in use elsewhere).
  - Supplier-specific fields like MOQs and manual overrides, where appropriate.

All schema changes must be idempotent and safe on activation/upgrade.

---

## 3. Forecast & demand engine (Phase 1)

### 3.1 Objective

Answer the question:

> “How much stock will I have left when the supplier shipment lands, and what
> should I order now to cover lead time plus buffer?”

### 3.2 Inputs per SKU

- Sales history for a configurable analysis period:
  - Default: 12 months.
  - Support shorter/longer ranges for new or special products.
- Current stock on hand.
- Supplier lead time (weeks).
- Optional buffer/coverage period (e.g. target 6-month cycle).
- `max_order_qty_per_month` ceiling (for sanity checks only).
- Stockout history (where plugin can infer or has logged it).

### 3.3 Stockouts & days on sale

Because stockouts distort demand:

- Detect when stock hits 0 and when it returns > 0.
- Log these intervals in a plugin table, at least for future data.
- For historical periods, use best-effort inference from available data.
- Calculate “days on sale” as (total days in range minus stockout days).

Demand per day/month must be based on **days on sale**, not raw time.

### 3.4 Calculations (baseline v1)

For each SKU assigned to the chosen supplier:

1. Determine total sales over the analysis window.
2. Compute demand per day or per month, adjusting for days-on-sale.
3. Project demand over:
   - Lead time (expressed as days).
   - Plus buffer period (e.g. additional months beyond lead time for 6-month
     stock).
4. Optionally apply weighting:
   - e.g. last 6 months weighted more heavily than the previous 6.
5. Calculate projected stock at arrival date:
   - `projected_stock = current_stock - expected_sales_until_arrival`.
6. Determine *raw* suggested order:
   - `needed = target_stock_level_at_arrival - projected_stock`.
7. Enforce boundaries:
   - If `needed <= 0`, suggestion can be zero.
   - Use `max_order_qty_per_month` × cycle length as an upper guardrail, but
     **do not overwrite the meta**.
8. Store the suggested quantity internally for display on the pre-order sheet.

Later phases may introduce more nuanced formulas, but v1 uses this structure.

---

## 4. Supplier configuration (Phase 2)

Per supplier, store and manage:

- Name / identifier.
- Currency (RMB, GBP, EUR, etc.).
- Lead time in weeks.
- Extra holiday delays (especially China national holidays).
- Allowed container types & nominal capacities (CBM).
- Default analysis period and buffer overrides (if any).
- Export layout configuration:
  - Which columns are shown.
  - Column order.

This is implemented as a dedicated admin settings screen under the plugin’s
menu, with rows or cards per supplier.

---

## 5. Containers & CBM (Phase 3)

### 5.1 Per-SKU volume

Using stored dimensions (cm):

- `cm³_per_unit = length × width × height`
- `cbm_per_unit = cm³_per_unit / 1,000,000`   

The plugin should calculate and cache CBM per unit where useful (or compute
on-the-fly if cheap enough).

### 5.2 Container & pallet logic

Per supplier:

- Define container types:
  - e.g. 20’, 40’, 40HQ, pallet.
- For each, store:
  - Nominal CBM capacity.
  - Optional “pallet reduction” factor (pallets reduce usable volume).

For a proposed order:

- Line CBM = `order_qty × cbm_per_unit`.
- Container CBM = sum of all line CBM.
- Fill % = `container_cbm / container_capacity × 100`.

### 5.3 Fill indicators

Define thresholds, e.g.:

- < 80%: under-filled (yellow).
- 80–100%: ideal range (green).
- > 100%: over capacity (red).

The pre-order sheet must display:

- A container fill bar / indicator.
- Numeric CBM and %.

Loose-fill products (e.g. CRF plastics) may be flagged or filterable as
“top-up” items to use remaining CBM headroom.

---

## 6. Pre-order sheet UI (Phase 2/3/4.1)

This is the main working screen and already partially exists in v1.5.5. :contentReference[oaicite:9]{index=9}  

### 6.1 Entry point

- User selects a supplier from a dropdown or supplier list.
- Optionally selects:
  - Analysis period.
  - Target container type.
  - Rebuild forecast / use cached results.

### 6.2 Core columns

Per SKU, the table should display:

- Product thumbnail.
- SKU.
- Product name.
- Brand.
- Category.
- Current stock.
- Forecasted demand.
- Suggested order quantity (read-only from engine).
- Editable final order quantity.
- Per-unit CBM.
- Line CBM (qty × per-unit CBM).
- Cost per unit (supplier currency).
- Line cost total.
- Optional:
  - MOQ.
  - Notes.
  - Flags (top seller, low volume, rounded).

Columns must be togglable and re-orderable (per supplier layout).

### 6.3 Sorting & filtering

Functions to support:

- Sorting by:
  - SKU, name, brand, category, stock, suggested qty, order qty, CBM, cost.
- Filtering:
  - Show only SKUs with suggested qty > 0.
  - Show only top sellers.
  - Show only low volume or overstock flags.
  - Show only rows not yet rounded.

### 6.4 Rounding toolbox

Rounding features:

- Global rounding: convert decimal suggestions to nearest whole number (up).
- Multi-select rounding:
  - Round to nearest 5 (up or down).
  - Round to nearest 10 (up or down).
- Mark rows that have had rounding applied (e.g. ✔ “Rounded”).
- Filter: “Show unrounded only” to catch remaining items.

All rows remain manually editable after rounding.

### 6.5 Integration with CBM

As quantities change:

- Recalculate line CBM, total CBM and container fill %.
- Live update of CBM bar/indicator at the top or bottom of the screen.

---

## 7. Export generation (Phase 2)

From the pre-order sheet, user can export:

- **CSV** – simple, robust, Excel-friendly.
- **XLSX** – likely via PhpSpreadsheet or similar, if acceptable.

Requirements:

- Per-supplier saved layout:
  - Toggle columns ON/OFF.
  - Control column order (simple 1…N system).
- Image handling:
  - For XLSX, embed thumbnail images where feasible.
  - For CSV, consider using image URLs or a plain text placeholder.
- Brand column:
  - Exportable and toggleable.
  - Included by default where the supplier expects it.

Export should mirror the user’s current supplier sheets closely to minimise
manual adjustments.

---

## 8. Goods-in / container receiving (Phase 5)

This is **optional** and must not block earlier phases.   

### 8.1 Import confirmed sheet

- Import a supplier sheet containing:
  - SKU (or product ID).
  - Final quantities.
  - Carton numbers (if present).
- Map columns to known fields.

### 8.2 Receiving UI

- Screen showing:
  - SKUs grouped/sortable by carton number, SKU, brand, category.
  - Expected qty vs received qty.
- User can:
  - Tick off lines as they are unpacked.
  - Adjust for shortages, over-shipments, substitutions.
  - Record damages and notes.

### 8.3 Stock updates & reporting

- On confirmation (e.g. end of day):
  - Compute `stock_delta` per SKU.
  - Apply bulk stock updates in WooCommerce.
- Generate:
  - Shortage report.
  - Damages report.
  - Supplier dispute summary.

---

## 9. Performance & scheduling

Forecasting and analytics can be heavy. Requirements:   

- Use plugin tables as caches for:
  - Sales snapshots.
  - Stockout logs.
  - Forecast results.
- Avoid recalculating heavy queries on every page load.
- Allow:
  - Manual “Rebuild forecast” buttons.
  - Scheduled runs via WP-Cron:
    - Daily / every few days / weekly (configurable).
- Admin settings must expose:
  - Which tasks are scheduled.
  - Frequency.
  - Last run timestamps.

If performance is an issue, user must be able to reduce frequency.

---

## 10. Phased implementation & alignment with v1.5.5

The development plan is:

1. **Phase 1 – Core Forecasting & Suggested Order Report**
   - Implement forecasting engine and stockout tracking.
   - Provide a read-only per-supplier report (even if not yet fully integrated into the pre-order UI).

2. **Phase 2 – Supplier Settings & Export Sheet**
   - Build supplier admin settings (currency, lead time, container types, layouts).
   - Integrate engine into the pre-order sheet.
   - Implement CSV/XLSX export using supplier layouts.

3. **Phase 3 – Container CBM & Capacity**
   - Harden CBM calculations and container capacity logic.
   - Improve CBM bar and warnings in the pre-order sheet.

4. **Phase 4 – Advanced Analytics & Refinements**
   - Top 25% seller logic and multipliers.
   - Low volume & overstock flags.
   - Visual cues in UI.
   - Comparison/reporting between plugin suggestions and `max_order_qty_per_month` to help the user refine their manual ceilings.

5. **Phase 5 – Goods-In / Container Receiving**
   - Import confirmed supplier sheets.
   - Receiving UI.
   - Bulk stock_delta updates.
   - Shortage/damage/dispute reports.

The existing v1.5.5 plugin already covers parts of Phases 2–3 (pre-order sheet,
supplier fields, early CBM logic). All future work should extend and tidy that
implementation rather than discarding it.

---
