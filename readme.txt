=== Stock Order ===
Contributors: Wilson Organisation Ltd
Version: 1.0.7
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
1.0.7: legacy helper hardening + embedded expiry cleanup playbook in System Status.
