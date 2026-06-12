<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\AuditLogger;
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

        if (! $invoice) {
            return $this->errorResponse('QR code was not found in invoice records.', [
                'qr_text' => ['The scanned QR code does not match any invoice record.'],
            ], 404);
        }

        return $this->successResponse([
            'record_type' => 'invoice',
            'invoice' => [
                'invoice_id'        => $invoice->invoice_id,
                'invoice_code'      => $invoice->invoice_code,
                'product_id'        => $invoice->product_id,
                'product_name'      => $invoice->product_name,
                'qr_text'           => $invoice->qr_text,
                'box_quantity'      => $invoice->target_box_count,
                'scanned_box_count' => $invoice->scanned_box_count,
                'match_box_count'   => $invoice->match_box_count,
                'pending_box_count' => $invoice->pending_box_count,
                'less_box_count'    => $invoice->less_box_count,
                'over_box_count'    => $invoice->over_box_count,
                'remaining_box_count' => max((int) $invoice->target_box_count - (int) $invoice->scanned_box_count, 0),
                'vendor_name'       => $invoice->vendor?->vendor_name,
                'status'            => $invoice->status,
            ],
            'verification' => [
                'status' => strtoupper((string) $invoice->status),
            ],
        ], 'Invoice found');
    }

    public function confirm(Request $request, Invoice $invoice): JsonResponse
    {
        $scannedBoxCount = (int) $invoice->scanned_box_count + 1;
        $targetBoxCount  = (int) $invoice->target_box_count;
        $matchBoxCount   = min($scannedBoxCount, $targetBoxCount);
        $overBoxCount    = max($scannedBoxCount - $targetBoxCount, 0);

        $invoice->forceFill([
            'scanned_box_count' => $scannedBoxCount,
            'match_box_count'   => $matchBoxCount,
            'pending_box_count' => 0,
            'less_box_count'    => 0,
            'over_box_count'    => $overBoxCount,
            'last_scanned_at'   => Carbon::now(),
            'status'            => 'on_progress',
        ])->save();

        AuditLogger::log(
            $request->user()?->user_id,
            'CONFIRM_SCAN',
            'invoices',
            $invoice->invoice_id,
            sprintf('Invoice %s confirmed. Count is now %d of %d', $invoice->invoice_code, $scannedBoxCount, $targetBoxCount)
        );

        return $this->successResponse([
            'invoice_id'        => $invoice->invoice_id,
            'status'            => $invoice->status,
            'scanned_box_count' => $scannedBoxCount,
            'target_box_count'  => $targetBoxCount,
            'match_box_count'   => $matchBoxCount,
            'pending_box_count' => 0,
            'less_box_count'    => 0,
            'over_box_count'    => $overBoxCount,
            'remaining_box_count' => max($targetBoxCount - $scannedBoxCount, 0),
        ], 'Scan confirmed successfully');
    }

    public function markPending(Request $request, Invoice $invoice): JsonResponse
    {
        $scannedBoxCount = (int) $invoice->scanned_box_count;
        $targetBoxCount = (int) $invoice->target_box_count;
        $matchBoxCount = min($scannedBoxCount, $targetBoxCount);
        $pendingBoxCount = max($targetBoxCount - $matchBoxCount, 0);
        $overBoxCount = max($scannedBoxCount - $targetBoxCount, 0);

        $invoice->update([
            'status' => 'pending',
            'match_box_count' => $matchBoxCount,
            'pending_box_count' => $pendingBoxCount,
            'less_box_count' => 0,
            'over_box_count' => $overBoxCount,
        ]);
        AuditLogger::log(
            $request->user()?->user_id,
            'MARK_INVOICE_PENDING',
            'invoices',
            $invoice->invoice_id,
            sprintf('Invoice %s marked pending with %d matched and %d pending boxes', $invoice->invoice_code, $matchBoxCount, $pendingBoxCount)
        );

        return $this->successResponse([
            'invoice_id' => $invoice->invoice_id,
            'status' => $invoice->status,
            'scanned_box_count' => $scannedBoxCount,
            'target_box_count' => $targetBoxCount,
            'match_box_count' => $matchBoxCount,
            'pending_box_count' => $pendingBoxCount,
            'less_box_count' => 0,
            'over_box_count' => $overBoxCount,
        ], 'Invoice scan marked as pending');
    }

    public function complete(Request $request, Invoice $invoice): JsonResponse
    {
        $scannedBoxCount = (int) $invoice->scanned_box_count;
        $targetBoxCount = (int) $invoice->target_box_count;
        $matchBoxCount = min($scannedBoxCount, $targetBoxCount);
        $lessBoxCount = max($targetBoxCount - $scannedBoxCount, 0);
        $overBoxCount = max($scannedBoxCount - $targetBoxCount, 0);

        $status = match (true) {
            $scannedBoxCount === $targetBoxCount => 'match',
            $scannedBoxCount < $targetBoxCount => 'less',
            default => 'over',
        };

        $invoice->update([
            'status' => $status,
            'match_box_count' => $matchBoxCount,
            'pending_box_count' => 0,
            'less_box_count' => $lessBoxCount,
            'over_box_count' => $overBoxCount,
        ]);
        AuditLogger::log(
            $request->user()?->user_id,
            'COMPLETE_INVOICE_SCAN',
            'invoices',
            $invoice->invoice_id,
            sprintf(
                'Invoice %s completed with status %s, matched %d, less %d, over %d',
                $invoice->invoice_code,
                strtoupper($status),
                $matchBoxCount,
                $lessBoxCount,
                $overBoxCount
            )
        );

        return $this->successResponse([
            'invoice_id' => $invoice->invoice_id,
            'status' => $invoice->status,
            'scanned_box_count' => $scannedBoxCount,
            'target_box_count' => $targetBoxCount,
            'match_box_count' => $matchBoxCount,
            'pending_box_count' => 0,
            'less_box_count' => $lessBoxCount,
            'over_box_count' => $overBoxCount,
        ], 'Invoice scan completed');
    }
}
