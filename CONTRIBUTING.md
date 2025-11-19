# Contributing Guidelines – Stock Order Plugin (SOP)

This document explains how to safely modify, expand, test, and commit changes to the SOP plugin to maintain quality and avoid regression issues.

---

## 📌 1. Workflow Summary

1. Make changes in VS Code.  
2. Test plugin in WordPress admin (ensure no PHP errors).  
3. Commit changes in GitHub Desktop with a clear message.  
4. Tag the commit when a feature or milestone is complete.  
5. Push to GitHub.

This workflow ensures rollback points and protects the project from accidental breakage.

---

## 📌 2. Coding Standards

### PHP
- Always begin files with:

  ```php
  <?php
  ```
- **Never** end files with `?>`.
- Use `esc_html()`, `esc_attr()`, `wp_kses()`, and `sanitize_*()` functions where appropriate.
- Match all `{}` brace pairs — no unclosed scope blocks.
- Follow WordPress naming conventions:
  - Functions: `sop_function_name()`
  - Classes: `SOP_Class_Name`
  - Variables: `$snake_case`

### HTML/JS
- Keep UI markup inside admin functions using PHP → HTML→ PHP sections.
- Ensure JS selectors are scoped to the correct wrapper/container.
- Avoid global JS unless necessary.

---

## 📌 3. Testing Guidelines

Before committing:
- Activate plugin without errors.
- Visit:
  - **Stock Order → Pre-Order Sheet**
  - **Stock Order → Forecast (Debug)**
  - **Stock Order → Products by Supplier**
- Confirm:
  - No PHP warnings/notices in WP Debug Log.
  - No JS console errors.
  - All admin pages load instantly.

---

## 📌 4. Commit Message Structure

Use the format:

```
[vX.Y.Z] Short description
- details...
```

Examples:
```
[v1.6.0] Added Supplier Mapping Bulk Actions
[v1.5.2] Hotfix - Preorder JS rounding bug
```

---

## 📌 5. Versioning & Tags

- Create a **new tag** only when a feature or milestone is completed.
- Use format:

```
vX.Y.Z-Short-Title
```

Examples:
```
v1.6.0-Product-Mapping-Enhanced
v1.7.0-Forecast-Integration
```

---

## 📌 6. Important Notes

- Do **not** modify the database tables manually.  
- Do **not** rename meta keys without updating all references.  
- Always test Pre-Order Sheet POST submission after changing anything.  
- Keep all admin pages under the Stock Order root menu.

---

## ✔ All changes MUST be committed via GitHub Desktop.

