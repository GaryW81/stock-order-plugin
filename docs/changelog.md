# Changelog

Versioning: VMAJOR.MINOR.PATCH (e.g. V5.7.9). Patch runs 0–9; after .9 the next release bumps the minor (e.g. 5.8.0), not 5.7.10.

## V5.8.7 - 2025-12-12 (Europe/London)
- PO XLS export now uses a full-width 5-column invoice layout; existing order-sheet XLS export (with images) remains unchanged.

## V5.8.6 - 2025-12-12 (Europe/London)
- Add separate Purchase Order (XLS) download button; Order Sheet export remains unchanged (images intact).

## V5.8.5 - 2025-12-12 (Europe/London)
- Forecast/SOQ handling days now start the day after the order date, aligning with the PO modal and fixing the 1-day undercount.

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
