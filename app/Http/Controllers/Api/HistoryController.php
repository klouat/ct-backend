<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with([
            'vendor',
            'boxes.locations',
        ])->orderByDesc('invoice_id');

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->integer('vendor_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));

            $query->where(function ($invoiceQuery) use ($search) {
                $invoiceQuery
                    ->where('invoice_code', 'like', '%'.$search.'%')
                    ->orWhere('product_id', 'like', '%'.$search.'%')
                    ->orWhere('product_name', 'like', '%'.$search.'%');
            });
        }

        $items = $query->paginate($this->perPage($request));

        return $this->successResponse([
            'items' => $items->getCollection()->map(fn (Invoice $invoice) => $this->transformItem($invoice))->all(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    private function transformItem(Invoice $invoice): array
    {
        $invoice->loadMissing([
            'vendor',
            'boxes.locations',
        ]);

        $latestLocation = $invoice->boxes
            ->flatMap(fn ($box) => $box->locations)
            ->sortByDesc('recorded_at')
            ->first();
        $status = strtoupper((string) ($invoice->status ?? 'not_scanned'));
        $vendorName = $invoice->vendor?->vendor_name ?? 'Unknown';

        $locationLabel = $latestLocation?->location_name;
        if (!$locationLabel) {
            if ($invoice->scanned_box_count > 0) {
                $locationLabel = 'Gudang Epson';
            } else {
                $locationLabel = 'Gudang ' . $vendorName;
            }
        }

        return [
            'invoice_id' => $invoice->invoice_id,
            'invoice_code' => $invoice->invoice_code,
            'product_id' => $invoice->product_id,
            'product_name' => $invoice->product_name,
            'quantity' => (int) $invoice->target_box_count,
            'box_quantity' => (int) $invoice->target_box_count,
            'scanned_box_count' => (int) $invoice->scanned_box_count,
            'match_box_count' => (int) $invoice->match_box_count,
            'pending_box_count' => (int) $invoice->pending_box_count,
            'less_box_count' => (int) $invoice->less_box_count,
            'over_box_count' => (int) $invoice->over_box_count,
            'location' => $locationLabel,
            'status' => $status,
            'vendor_name' => $invoice->vendor?->vendor_name,
            'qr_text' => $invoice->qr_text,
            'recorded_at' => $latestLocation?->recorded_at,
            'last_scanned_at' => $invoice->last_scanned_at?->toIso8601String(),
            'created_at' => $invoice->created_at?->toIso8601String(),
        ];
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
