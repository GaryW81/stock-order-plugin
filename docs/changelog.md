# Changelog

Versioning: VMAJOR.MINOR.PATCH (e.g. V5.7.9). Patch runs 0–9; after .9 the next release bumps the minor (e.g. 5.8.0), not 5.7.10.

## V5.8.4 - 2025-12-12 (Europe/London)
- Fix: PO modal handling days no longer count the order date; short lead times now behave as “nights” (e.g. 1 day handling + 1 day shipping = 2 nights).

## V5.8.3 - 2025-12-12 (Europe/London)
- Supplier lead time supports days/weeks and is used for PO date suggestions and forecasting lead maths.

## V5.8.2 - 2025-12-12 (Europe/London)
- Fix: PO modal dates now recalculate when order date changes for non-RMB suppliers (e.g. GBP).

## V5.8.1 - 2025-12-12 (Europe/London)
- Fix: Supplier holiday day fields default blank (no “0”), so Update supplier works with no holidays set.

## V5.8.0 - 2025-12-12 (Europe/London)
- Fix: PO modal edits now reliably trigger the unsaved changes warning (refresh/back).

## V5.7.9 - 2025-12-12 (Europe/London)
- Restore changelog file and restore version scheme after accidental 0.1.4 bump.

## V5.7.8 - 2025-12-12 (Europe/London)
- PO modal: clarify balance FX dependency / tip.
