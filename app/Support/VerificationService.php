<?php

namespace App\Support;

use App\Models\Box;
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
        $box->loadMissing('packages.countingResults');

        $totalQty = 0;
        $totalCounted = 0;

        foreach ($box->packages as $package) {
            $totalQty += (int) $package->qty;
            $totalCounted += (int) ($package->countingResults->sortByDesc('counted_time')->first()?->counted_qty ?? 0);
        }

        return [
            'box_id' => $box->box_id,
            'box_code' => $box->box_code,
            'total_packages' => $box->packages->count(),
            'total_qty' => $totalQty,
            'total_counted' => $totalCounted,
            'status' => self::getStatus($totalQty, $totalCounted),
        ];
    }

    public static function shipment(Shipment $shipment): array
    {
        $shipment->loadMissing('boxes.packages.countingResults');

        $totalBoxes = $shipment->boxes->count();
        $totalPackages = 0;
        $totalQty = 0;
        $totalCounted = 0;

        foreach ($shipment->boxes as $box) {
            $totalPackages += $box->packages->count();

            foreach ($box->packages as $package) {
                $totalQty += (int) $package->qty;
                $totalCounted += (int) ($package->countingResults->sortByDesc('counted_time')->first()?->counted_qty ?? 0);
            }
        }

        return [
            'shipment_id' => $shipment->shipment_id,
            'shipment_code' => $shipment->shipment_code,
            'total_boxes' => $totalBoxes,
            'total_packages' => $totalPackages,
            'total_qty' => $totalQty,
            'total_counted' => $totalCounted,
            'status' => self::getStatus($totalQty, $totalCounted),
        ];
    }
}
