# Stock Order Plugin – Architecture Overview

This document provides a high-level and file-level breakdown of the SOP plugin’s structure and relationships.

---

# 📁 Folder Structure

```
/stock-order-plugin/
│
├── stock-order-plugin.php         # Loader + activation hooks
│
├── README.md
├── VERSIONS.md
├── CONTRIBUTING.md
├── ARCHITECTURE.md
├── TODO.md
│
├── /includes/                     # Backend logic (core)
│   ├── db-helpers.php
│   ├── domain-helpers.php
│   ├── helper-buffer.php
│   ├── forecast-core.php
│   ├── supplier-meta-box.php
│
└── /admin/                        # Admin UI screens
    ├── settings-supplier.php
    ├── product-mapping.php
    ├── preorder-core.php
    ├── preorder-ui.php
```

---

# 🧠 Module Breakdown

## 1. Loader – `stock-order-plugin.php`
- Defines constants.
- Loads includes + admin files.
- Activation hook triggers DB installation.
- Central entry point for WP.

---

## 2. Database Layer – `includes/db-helpers.php`
### Purpose:
Defines and manages custom DB tables:

- `sop_suppliers`
- `sop_stockout_log`
- `sop_forecast_cache`
- `sop_forecast_cache_items`
- `sop_goods_in_sessions`
- `sop_goods_in_items`
- `sop_supplier_layouts`

### Exposes:
- Insert / Update / Delete / Get methods for all tables.

This is the backbone of SOP.

---

## 3. Domain Helpers – `includes/domain-helpers.php`
### Purpose:
Higher-level logic on top of DB:

- Supplier CRUD abstraction  
- Stockout open/close tracking  
- Goods-in session creation  
- Forecast caching utilities  

This decouples business logic from raw DB.

---

## 4. Buffer & Analysis – `includes/helper-buffer.php`
### Purpose:
- Calculate effective buffer months  
- Combine global settings + supplier override  
- Compute lookback days  

This feeds into forecasting.

---

## 5. Forecast Engine – `includes/forecast-core.php`
### Purpose:
- Sales lookup from Woo tables  
- Demand-per-day calculations  
- Lead time + buffer → forecast window  
- Suggested qty (raw + capped)  

### UI:
- Stock Order → Forecast (Debug)

This page gives visibility into the forecasting logic.

---

## 6. Pre-Order Core – `admin/preorder-core.php`
### Purpose:
Processes:

- Supplier currency mapping  
- Cost normalisation  
- Container CBM logic  
- Row builder (location, brand, dims, cubic, costs)  
- Handler for POST (saving all values)

This module powers the Pre-Order Sheet.

---

## 7. Pre-Order UI – `admin/preorder-ui.php`
### Purpose:
UI/UX for ordering:

- Table  
- Sorting  
- Column toggles  
- Summary bar  
- Rounding  
- Locking  
- JS dynamic totals  

The largest front-end component of SOP.

---

## 8. Product Mapping – `admin/product-mapping.php`
### Purpose:
- Lists all products mapped to supplier  
- Lists unassigned products  
- Paginated view  
- Thumbnail + SKU + supplier info  
- Foundation for future bulk actions  

---

# 🔗 How Components Interact

```
WooCommerce Orders → Forecast Core → Suggested Qty
         ↑                                   ↓
 Stockout Log                    Pre-Order Sheet Logic
         ↑                                   ↓
       Domain Helpers ← DB Helpers → Supplier Helpers
```

Everything flows down from:

**Woo Data → Forecast Engine → Pre-Order Sheet → Export/Ordering**

---

# 🚀 Future Architecture Expansions

- Supplier Layouts logic  
- Automatic forecast injection into Pre-Order rows  
- Export model (CSV/XLSX)  
- Goods-In → Auto stock updates  

