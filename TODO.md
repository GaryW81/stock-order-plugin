# SOP Plugin – TODO / Roadmap

This file lists active tasks, future improvements, and planned features for the SOP plugin.

---

# 🔥 HIGH PRIORITY NEXT STEPS

## ☐ 1. Integrate Forecast SOQ into Pre-Order Sheet
- Show forecast qty in a new column (SOQ)
- Toggle between “SOQ” and “Manual Qty”
- Optional “Apply SOQ” button per-row or bulk

## ☐ 2. Improve Product Mapping Screen
- Bulk assign supplier  
- Inline supplier dropdown for each product  
- Add filters: brand, category, low stock, etc.  

## ☐ 3. Supplier Layouts (Export Configuration)
- Enable suppliers to define which columns appear in exported order files  
- Save config into `sop_supplier_layouts`  
- Add UI screen for customization  

---

# 🟦 MEDIUM PRIORITY

## ☐ 4. Export Tools (CSV / Excel)
- Generate clean supplier order sheets from Pre-Order table  
- Use column layouts  
- Export per-supplier or per-container  

## ☐ 5. Goods-In Enhancements
- UI for Goods-In sessions  
- Mark quantities received/missing/damaged  
- Auto-create a WC stock adjustment  

## ☐ 6. Testing Tools
- Add admin-only test actions  
- Interrupt/resume forecast runs  
- Data validation tools  

---

# 🟧 LOW PRIORITY / FUTURE IDEAS

## ☐ 7. REST API Endpoints
- For external apps to pull forecast or pre-order sheet data  

## ☐ 8. Settings UI v2
- Allow editing of global lookback period  
- Allow editing of buffer months per supplier  
- Currency rate automation (API fetch)  

## ☐ 9. UI Polish
- Collapsible sections  
- Sticky summary bars  
- Configurable table size limits  

---

# 🧹 COMPLETED TASKS

### ✔ Pre-Order Core  
### ✔ Pre-Order UI  
### ✔ Forecast Core  
### ✔ Mapping Screen foundation  
### ✔ DB helper layer  
### ✔ Domain helper layer  
### ✔ Activation fixes  
### ✔ Versioning / Tags / Documentation  

---

# 📝 Notes
Update this file after each feature completion. Keep roadmap structured and realistic.  
