<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Package as SvsPackage;
use App\Models\ScanLog;
use App\Support\AuditLogger;
use App\Support\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScanController extends Controller
{
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_text' => ['required', 'string'],
            'user_id' => ['nullable', 'exists:users,user_id'],
        ]);

        $invoice = Invoice::with(['vendor', 'boxes'])
            ->where('qr_text', $validated['qr_text'])
            ->first();

        if ($invoice) {
            $invoice->forceFill([
                'scanned_box_count' => (int) $invoice->scanned_box_count + 1,
                'last_scanned_at' => Carbon::now(),
                'status' => 'on_progress',
            ])->save();

            return $this->successResponse([
                'record_type' => 'invoice',
                'invoice' => [
                    'invoice_id' => $invoice->invoice_id,
                    'invoice_code' => $invoice->invoice_code,
                    'product_id' => $invoice->product_id,
                    'product_name' => $invoice->product_name,
                    'qr_text' => $invoice->qr_text,
                    'box_quantity' => $invoice->target_box_count,
                    'scanned_box_count' => $invoice->scanned_box_count,
                    'remaining_box_count' => max((int) $invoice->target_box_count - (int) $invoice->scanned_box_count, 0),
                    'vendor_name' => $invoice->vendor?->vendor_name,
                    'status' => $invoice->status,
                ],
                'verification' => [
                    'status' => strtoupper((string) $invoice->status),
                ],
            ], 'Invoice scanned successfully');
        }

        $package = SvsPackage::with(['box.invoice', 'box.vendor'])->where('qr_text', $validated['qr_text'])->firstOrFail();
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
                'invoice_po_number' => $package->box->invoice?->po_number,
                'vendor_name' => $package->box->vendor?->vendor_name,
            ],
            'verification' => VerificationService::package($package),
        ], 'Package scanned successfully');
    }

    public function markPending(Request $request, Invoice $invoice): JsonResponse
    {
        $invoice->update([
            'status' => 'pending',
        ]);

        return $this->successResponse([
            'invoice_id' => $invoice->invoice_id,
            'status' => $invoice->status,
            'scanned_box_count' => (int) $invoice->scanned_box_count,
            'target_box_count' => (int) $invoice->target_box_count,
        ], 'Invoice scan marked as pending');
    }

    public function complete(Request $request, Invoice $invoice): JsonResponse
    {
        $scannedBoxCount = (int) $invoice->scanned_box_count;
        $targetBoxCount = (int) $invoice->target_box_count;

        $status = match (true) {
            $scannedBoxCount === $targetBoxCount => 'match',
            $scannedBoxCount < $targetBoxCount => 'less',
            default => 'over',
        };

        $invoice->update([
            'status' => $status,
        ]);

        return $this->successResponse([
            'invoice_id' => $invoice->invoice_id,
            'status' => $invoice->status,
            'scanned_box_count' => $scannedBoxCount,
            'target_box_count' => $targetBoxCount,
        ], 'Invoice scan completed');
    }
}
