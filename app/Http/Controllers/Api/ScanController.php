<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package as SvsPackage;
use App\Models\ScanLog;
use App\Support\AuditLogger;
use App\Support\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_text' => ['required', 'string', 'exists:packages,qr_text'],
            'user_id' => ['nullable', 'exists:users,user_id'],
        ]);

        $package = SvsPackage::with('box.shipment.vendor')->where('qr_text', $validated['qr_text'])->firstOrFail();
        $actingUser = $request->user();
        $scanUserId = $validated['user_id'] ?? $actingUser?->user_id;

        ScanLog::create([
            'package_id' => $package->package_id,
            'user_id' => $scanUserId,
            'status' => 'SCANNED',
        ]);

        AuditLogger::log($actingUser?->user_id, 'SCAN_PACKAGE', 'scan_logs', $package->package_id, 'Package scanned');

        return $this->successResponse([
            'package' => [
                'package_id' => $package->package_id,
                'package_code' => $package->package_code,
                'qr_text' => $package->qr_text,
                'qty' => $package->qty,
                'box_code' => $package->box->box_code,
                'shipment_code' => $package->box->shipment->shipment_code,
                'vendor_name' => $package->box->shipment->vendor->vendor_name,
            ],
            'verification' => VerificationService::package($package),
        ], 'Package scanned successfully');
    }
}
