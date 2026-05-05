<?php

namespace App\Support;

use App\Models\Box;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Shipment;

class VerificationService
{
    public static function getStatus(int $expectedQty, int $countedQty): string
    {
        return match (true) {
            $countedQty === $expectedQty => 'MATCH',
            $countedQty < $expectedQty => 'MISSING',
            default => 'OVER',
        };
    }

    public static function package(Package $package): array
    {
        $latestCount = $package->countingResults()->latest('counted_time')->first();
        $countedQty = (int) ($latestCount?->counted_qty ?? 0);

        return [
            'package_id' => $package->package_id,
            'package_code' => $package->package_code,
            'qty' => (int) $package->qty,
            'counted_qty' => $countedQty,
            'status' => self::getStatus((int) $package->qty, $countedQty),
            'qr_text' => $package->qr_text,
            'last_counted_time' => $latestCount?->counted_time,
        ];
    }

    public static function box(Box $box): array
    {
        $box->loadMissing('packages.countingResults', 'items');

        $totalQty = 0;
        $totalCounted = 0;

        foreach ($box->packages as $package) {
            $totalQty += (int) $package->qty;
            $totalCounted += (int) ($package->countingResults->sortByDesc('counted_time')->first()?->counted_qty ?? 0);
        }

        return [
            'box_id' => $box->box_id,
            'box_code' => $box->box_code,
            'qr_text' => $box->qr_text,
            'total_packages' => $box->packages->count(),
            'total_items' => $box->items->count(),
            'total_qty' => $totalQty,
            'total_counted' => $totalCounted,
            'status' => self::getStatus($totalQty, $totalCounted),
        ];
    }

    public static function invoice(Invoice $invoice): array
    {
        $invoice->loadMissing('boxes.packages.countingResults', 'boxes.items');

        $totalBoxes = $invoice->boxes->count();
        $totalItems = 0;
        $totalPackages = 0;
        $totalQty = 0;
        $totalCounted = 0;

        foreach ($invoice->boxes as $box) {
            $totalItems += $box->items->count();
            $totalPackages += $box->packages->count();

            foreach ($box->items as $item) {
                $totalQty += (int) $item->quantity;
            }

            foreach ($box->packages as $package) {
                $totalCounted += (int) ($package->countingResults->sortByDesc('counted_time')->first()?->counted_qty ?? 0);
            }
        }

        return [
            'invoice_id' => $invoice->invoice_id,
            'invoice_code' => $invoice->invoice_code,
            'po_number' => $invoice->po_number,
            'status' => $invoice->status,
            'target_box_count' => (int) $invoice->target_box_count,
            'total_boxes' => $totalBoxes,
            'total_items' => $totalItems,
            'total_packages' => $totalPackages,
            'total_qty' => $totalQty,
            'total_counted' => $totalCounted,
            'status' => self::getStatus($totalQty, $totalCounted),
        ];
    }

    public static function shipment(Shipment $shipment): array
    {
        return [
            'shipment_id' => $shipment->shipment_id,
            'shipment_code' => $shipment->shipment_code,
            'total_boxes' => 0,
            'total_packages' => 0,
            'total_qty' => 0,
            'total_counted' => 0,
            'status' => 'PENDING',
        ];
    }
}
