# VerificationServiceTest

This document explains what `tests/Unit/VerificationServiceTest.php` verifies.

## Purpose

The test covers the shipment summary logic inside `App\Support\VerificationService::shipment()`.

It checks that the service:

- counts the number of boxes in a shipment
- counts the number of packages across those boxes
- sums the expected package quantities
- returns the correct verification status

## Test Scenario

The test builds an in-memory shipment structure with:

- 1 shipment
- 1 box
- 2 packages

Package data used in the test:

- `PKG-001` has expected quantity `5`
- `PKG-001` has one count record: `6`
- `PKG-002` has expected quantity `2`
- `PKG-002` has one count record: `4`

## Expected Result

- `PKG-001` contributes `6`
- `PKG-002` contributes `4`

That produces:

- `total_boxes = 1`
- `total_packages = 2`
- `total_qty = 7`
- `total_counted = 10`

Because counted quantity `10` is greater than expected quantity `7`, the returned status must be `OVER`.

## Why This Test Matters

This test protects the shipment aggregation logic from regressions by checking that totals and final status are calculated correctly.
