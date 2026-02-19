=== Stock Order ===
Contributors: Wilson Organisation Ltd
Version: 1.0.18
Requires at least: 5.9
Tested up to: 6.6
Requires PHP: 8.1

Internal tool for supplier ordering, forecasting, purchase orders, container/CBM planning, optional goods-in, and labels/barcodes.

== Description ==
Stock Order is an internal WooCommerce admin tool for Wilson-Organisation Ltd. It supports supplier setup, demand forecasting, pre-order sheets, container capacity planning, and exporting order sheets. Optional modules include goods-in/receiving and labels/barcodes.

== Installation ==
1. Upload the plugin to the /wp-content/plugins/ directory.
2. Activate through the WordPress admin Plugins screen.

== Requirements ==
- WordPress + WooCommerce (internal/private use only).

== Notes ==
- Internal/private plugin; no public distribution.
- No automatic uninstall cleanup.

== Changelog ==
1.0.1 - Dependency guards (WooCommerce + main site only), cron cleanup on deactivation, plugin header polish, Settings link.
1.0.2 - Hotfix: parent-site guard uses wilson-organisation.com host suffix (fixes missing menu/access denied on multisite setups).
1.0.3 - Release hygiene: add ABSPATH guards across admin/includes modules.
1.0.4 - Add System Status tab (diagnostics), centralised notices + last bootstrap error panel, capability helper for consistent access checks.
1.0.5 - Add downloadable System Status debug report + minor UI polish + capability consistency.
1.0.6 - System Status: show legacy product history usage + expiry date + cleanup checklist.
1.0.7 - Legacy helper hardening + embedded expiry cleanup playbook in System Status.
1.0.8 - Fix product edit 'No supplier' label encoding.
1.0.9 - Goods-In: add completion confirmation (tick + confirm) and server-side enforcement.
1.0.10 - Goods-In: Add now delta input (blank by default), live Stocked/Outstanding updates after apply, and blocked stock decreases.
1.0.11 - Goods-In: add per-line correction (safe decrease) with confirmation modal; keep bulk apply increase-only.
1.0.12 - Goods-In: auto-select rows on Add now entry; clarify correction modal usage.
1.0.13 - Goods-In: align Add now input with other qty fields (Total label no longer shifts layout).
1.0.14 - Goods-In modal now shows Forecast Demand instead of Buffer stock (Forecast Debug metric).
1.0.15 - Goods-In: product name links to front-end page + add Edit link under name.
1.0.16 - Goods-In: tidy Product column link layout (front-end name link + Edit link beneath).
1.0.17 - Goods-In: product row modal no longer scrolls page to top; preserves scroll position when opening/closing.
1.0.18 - Goods-In: add Forecast demand column (after Carton no.) to avoid opening modal.
