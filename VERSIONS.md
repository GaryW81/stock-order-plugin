# Stock Order Plugin (SOP)
## Version History & Milestones

This document tracks all major and minor changes to the SOP plugin, including stable milestones, feature additions, fixes, and roadmap markers.  
All versions below correspond to Git tags in the repository.

---

## 🔵 v1.5.1 — Activation Fixes & Stable Pre-Order / Forecast  
**(Latest Stable Version)**  
**Date:** 2025-11-19  

### Changes  
- Added missing PHP `<?php` opening tags across admin and includes files.  
- Fixed multiple unclosed braces `{}` and EOF parse errors.  
- Cleaned duplicated function definitions (specifically `sop_preorder_render_admin_page`).  
- Verified and corrected `forecast-core.php`, `preorder-core.php`, and `preorder-ui.php`.  
- Plugin now activates cleanly with no PHP fatal errors.  
- Fully stable Pre-Order Sheet UI and Forecast Debug Screen.  

---

## 🔵 v1.5.0 — Pre-Order Sheet UI Completed  
**Date:** 2025-11-18  

### Features  
- Complete UI for admin Pre-Order Sheet.  
- Sorting, rounding, column visibility toggles.  
- Live total recalculation (JS).  
- Sticky header and 90vh scroll container.  
- Container CBM calculations, allowance %, pallet layer adjustments.  
- Sheet locking and unlocking.  

---

## 🔵 v1.4.0 — Pre-Order Sheet Core  
**Date:** 2025-11-16  

### Features  
- Pre-Order POST handler: saves SKU, MOQ, Notes, Order Qty, and Supplier Cost.  
- Normalised supplier cost storage per currency (RMB, USD, EUR, GBP).  
- Supplier currency mapping logic.  
- Container CBM, product cubic calculations, brand/location retrieval.  
- Row builder function for Pre-Order Sheet.

---

## 🔵 v1.3.0 — Forecast Core Engine & Forecast Debug Screen  
**Date:** 2025-11-15  

### Features  
- Implemented `Stock_Order_Plugin_Core_Engine` singleton.  
- Forecast logic:
  - Sales lookup using WooCommerce order tables  
  - Demand-per-day logic  
  - Lead time + buffer month calculation  
  - Suggested order quantities (raw + capped)  
- Forecast Debug screen with scrollable table + sticky head.  
- Supplier dropdown with ID mapping.  

---

## 🔵 v1.2.0 — Domain-Level Helpers  
**Date:** 2025-11-15  

### Features  
- Supplier helpers (`upsert`, `get_by_id`, etc.)  
- Stockout system (`open`, `close`, log behavior)  
- Goods-In Session + Item helpers  
- Forecast cache insert + update helpers  

---

## 🔵 v1.1.0 — DB Helpers & Table Installer  
**Date:** 2025-11-15  

### Features  
- `sop_DB` class with dbDelta installer.  
- Defines all SOP tables:
  - `sop_suppliers`
  - `sop_stockout_log`
  - `sop_forecast_cache`
  - `sop_forecast_cache_items`
  - `sop_goods_in_sessions`
  - `sop_goods_in_items`
  - `sop_supplier_layouts`
- Generic CRUD helpers (insert, update, delete, get_row, get_results).  

---

## 🔵 v1.0.0 — Initial SOP Plugin Structure  
**Date:** 2025-11-15  

### Features  
- Base plugin loader.  
- Activation/deactivation hooks.  
- Includes structure:
  - `/includes`
  - `/admin`
- Basic wiring of modules (DB, helpers, UI stubs).  

---

# 🔮 Roadmap (Future Milestones)

## v1.6.0 — Enhanced Product Mapping  
- Supplier reassignment and bulk mapping tools.  
- Inline editing of supplier ID per product.  
- Additional filters (category, brand, status).  

## v1.7.0 — Forecast → Pre-Order Integration  
- Display forecast suggested order quantities inside Pre-Order Sheet.  
- Toggle between manual vs forecast SOQ.  

## v1.8.0 — Supplier Layouts & Export Tools  
- Build UI for supplier export configuration.  
- CSV/Excel output for pre-order sheets.  

## v2.0.0 — SOP Phase 1 Full Release  
- Full feature consolidation and cleanup.  
- Formal README + documentation complete.  

---

# Notes
- This file should be updated **every time** a new Git tag is created.  
- Use the version format: `vX.Y.Z-Short-Description`  
- Keep descriptions brief and consistent.  

