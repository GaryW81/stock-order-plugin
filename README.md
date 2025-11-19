# Stock Order Plugin (SOP)
A private, internal-use WordPress + WooCommerce plugin designed for **supplier management**, **forecasting**, **pre-order planning**, and **container-based ordering**.

This plugin is a custom ERP-style system built specifically for DBRacing / PitBikeShop to automate and optimise stock ordering workflows.

---

# 📦 Features Overview

## ⭐ **1. Supplier Management**
- Supplier table stored in custom DB (`sop_suppliers`)  
- Fields:
  - Name, Slug, Currency  
  - Lead time (weeks)  
  - Supplier code  
  - Optional buffer override  
- Helper functions:
  - `sop_supplier_upsert()`  
  - `sop_supplier_get_by_id()`  
  - `sop_supplier_get_all()`  

---

## ⭐ **2. Forecast Engine**
Located in `includes/forecast-core.php`:

- Uses real WooCommerce `wc_order_product_lookup` + `wc_orders` data  
- Computes:
  - Qty sold  
  - Days on sale  
  - Demand-per-day  
  - Forecast demand  
  - Suggested order quantities  
  - Max order caps  
- Supplier-aware (lead time + holiday + buffer settings)

### Forecast Debug Screen
Admin → **Stock Order → Forecast (Debug)**:
- Supplier dropdown  
- Full scrollable results table  
- Excellent for diagnostics  

---

## ⭐ **3. Pre-Order Sheet**
Located in `admin/preorder-ui.php` and `admin/preorder-core.php`:

### Features:
- Supplier filter  
- Container selection (20ft, 40ft, 40ft HC)  
- Allowance % + pallet layer  
- CBM consumption bar  
- Stock, inbound, MOQ, manual order qty  
- Cubic cm and Line CBM calculations  
- Per-unit cost in supplier currency  
- Notes per product  
- SKU editing  
- Column visibility toggles  
- Rounding controls (up/down, step 5/10)  
- Sheet locking per supplier  

### Saves to:
- `_sop_preorder_notes`  
- `_sop_min_order_qty`  
- `_sop_preorder_order_qty`  
- `_sop_cost_rmb`, `_sop_cost_usd`, `_sop_cost_eur`, `_cogs_value`  

---

## ⭐ **4. Product Mapping (Phase 2)**
Admin → **Stock Order → Products by Supplier**

- View all products assigned to each supplier  
- “Unassigned products” view for cleanup  
- Pagination (200 per page)  
- Brand/SKU/Thumbnail view  
- Useful for verifying all products are mapped  
- Future: inline edits + bulk mapping  

---

## ⭐ **5. Database Structure**

### Tables Created by SOP:
| Table | Purpose |
|-------|---------|
| `sop_suppliers` | Supplier master record |
| `sop_stockout_log` | Log when products go out of stock |
| `sop_forecast_cache` | Stores forecast run metadata |
| `sop_forecast_cache_items` | Stores per-product forecast output |
| `sop_goods_in_sessions` | Goods-in container/session header |
| `sop_goods_in_items` | Items received per container |
| `sop_supplier_layouts` | For future export customisation |

Database creation handled via **dbDelta** on activation.

---

# ⚙️ Plugin Architecture

## `/stock-order-plugin.php`  
Main loader:
- Defines constants  
- Loads includes  
- Registers activation/deactivation  
- Wires admin pages  

## `/includes/…`  
Core backend logic:
- Database helpers  
- Domain-level helpers  
- Forecast engine  
- Supplier meta box  
- Helper logic for buffer + analysis  

## `/admin/…`  
Admin interface:
- Settings pages  
- Product mapping  
- Pre-Order Sheet  
- Forecast debug screen  

---

# 🛠 Requirements
- WordPress 6.x  
- WooCommerce 8.x+  
- PHP 8.0+  
- MySQL with InnoDB  
- WooCommerce enabled (forecast relies on Woo revenue tables)

---

# 👨‍💻 Development Notes

### Versioning  
Use semantic tags:  
`vX.Y.Z-Short-Title`  
Tags stored in GitHub.

### Commit Style  
```
[v1.6.0] Added Supplier Mapping UI
- details...
```

### File Formatting  
- All PHP files must start with `<?php`  
- No trailing PHP `?>` at end of files  
- Ensure brace matching for stability  
- Use WordPress escaping functions (`esc_html`, `esc_attr`)  

---

# 🔮 Future Plans
- Real-time forecast integration in Pre-Order Sheet  
- Supplier Export Layouts  
- Multi-container planning  
- Goods-In → Auto stock adjustments  
- Better product mapping automation  
- Rest API endpoints for external tools  

---

# © License
Private, internal-only use.  
Not for redistribution.  

