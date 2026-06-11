<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vendor;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['vendor', 'boxes.vendor', 'boxes.items'])->orderByDesc('invoice_id');
        $user = $request->user();

        if ($request->filled('po_number')) {
            $query->where('po_number', 'like', '%'.$request->string('po_number')->trim().'%');
        }

        if ($request->filled('invoice_code')) {
            $query->where('invoice_code', 'like', '%'.$request->string('invoice_code')->trim().'%');
        }

        if ($request->filled('estimated_arrival_date')) {
            $query->whereDate('estimated_arrival_date', $request->date('estimated_arrival_date'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_code' => [
                'required', 'string', 'max:100',
                \Illuminate\Validation\Rule::unique('invoices')->where(function ($query) use ($request) {
                    return $query->where('product_id', $request->product_id);
                })
            ],
            'po_number' => ['required', 'string', 'max:100', 'unique:invoices,po_number'],
            'target_box_count' => ['required', 'integer', 'min:1'],
            'estimated_arrival_date' => ['nullable', 'date'],
            'vendor' => ['nullable', 'string', 'max:100'],
            'vendor_name' => ['nullable', 'string', 'max:100'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,vendor_id'],
            'product_id' => ['required', 'string', 'max:100'],
            'product_name' => ['required', 'string', 'max:150'],
        ]);
        $vendorId = $this->resolveVendorId($request->user(), $validated, 'vendor');

        $invoice = DB::transaction(function () use ($request, $validated, $vendorId) {
            $invoice = Invoice::create([
                'invoice_code' => $validated['invoice_code'],
                'po_number' => $validated['po_number'],
                'vendor_id' => $vendorId,
                'product_id' => trim($validated['product_id']),
                'product_name' => trim($validated['product_name']),
                'qr_text' => $this->buildInvoiceQrText(
                    trim($validated['invoice_code']),
                    trim($validated['product_id']),
                    $vendorId
                ),
                'target_box_count' => $validated['target_box_count'],
                'scanned_box_count' => 0,
                'match_box_count' => 0,
                'pending_box_count' => 0,
                'less_box_count' => 0,
                'over_box_count' => 0,
                'last_scanned_at' => null,
                'estimated_arrival_date' => $validated['estimated_arrival_date'] ?? null,
                'status' => 'not_scanned',
            ]);

            $box = $this->createInvoiceBox($invoice, $vendorId);

            BoxItem::create([
                'box_id' => $box->box_id,
                'sku' => $this->generateSku(
                    $invoice->invoice_code,
                    trim($validated['product_id']),
                    trim($validated['product_name'])
                ),
                'item_name' => trim($validated['product_name']),
                'quantity' => (int) $validated['target_box_count'],
            ]);

            AuditLogger::log($request->user()->user_id, 'CREATE_INVOICE', 'invoices', $invoice->invoice_id, 'Invoice created with box items');

            return $invoice;
        });

        return $this->successResponse(
            $invoice->load(['vendor', 'boxes.vendor', 'boxes.items']),
            'Invoice created successfully',
            201
        );
    }

    private function createInvoiceBox(Invoice $invoice, int $vendorId): Box
    {
        $existingBox = Box::withTrashed()
            ->where('invoice_id', $invoice->invoice_id)
            ->first();

        if ($existingBox && $existingBox->trashed()) {
            $existingBox->restore();
        }

        if (! $existingBox) {
            return Box::create([
                'invoice_id' => $invoice->invoice_id,
                'vendor_id' => $vendorId,
            ]);
        }

        $existingBox->update([
            'vendor_id' => $vendorId,
        ]);

        return $existingBox->fresh();
    }

    private function resolveVendorId(User $user, array $entry, string $entryKey): int
    {
        if (isset($entry['vendor_id'])) {
            $vendor = Vendor::find($entry['vendor_id']);

            if (! $vendor) {
                throw ValidationException::withMessages([
                    $entryKey.'.vendor_id' => ['The selected vendor_id is invalid.'],
                ]);
            }

            return (int) $vendor->vendor_id;
        }

        $vendorName = trim((string) ($entry['vendor_name'] ?? $entry['vendor'] ?? ''));

        if ($vendorName === '') {
            throw ValidationException::withMessages([
                $entryKey.'.vendor' => ['Vendor is required.'],
            ]);
        }

        $vendor = Vendor::where('vendor_name', $vendorName)->first();

        if (! $vendor) {
            throw ValidationException::withMessages([
                $entryKey.'.vendor' => ['The specified vendor was not found.'],
            ]);
        }

        return (int) $vendor->vendor_id;
    }

    private function buildInvoiceQrText(string $invoiceCode, string $productId, int $vendorId): string
    {
        return sprintf(
            'INV:%s|PRODUCT:%s|VENDOR:%d',
            strtoupper($invoiceCode),
            strtoupper($productId),
            $vendorId
        );
    }

    private function generateSku(string $invoiceCode, string $productId, string $productName): string
    {
        $invoiceFragment = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $invoiceCode) ?: 'INV', 0, 24));
        $productIdFragment = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $productId) ?: 'PRODUCT', 0, 24));
        $productNameFragment = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $productName) ?: 'ITEM', 0, 24));

        return sprintf(
            'SKU-%s-%s-%s',
            $invoiceFragment,
            $productIdFragment,
            $productNameFragment
        );
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
