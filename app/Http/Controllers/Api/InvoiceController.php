<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Box;
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
        $query = Invoice::with(['boxes.vendor', 'boxes.items'])->orderByDesc('invoice_id');
        $user = $request->user();

        if ($user->role === 'VENDOR' && $user->vendor_id) {
            $query->whereHas('boxes', fn ($boxQuery) => $boxQuery->where('vendor_id', $user->vendor_id));
        }

        if ($request->filled('po_number')) {
            $query->where('po_number', 'like', '%'.$request->string('po_number')->trim().'%');
        }

        if ($request->filled('invoice_code')) {
            $query->where('invoice_code', 'like', '%'.$request->string('invoice_code')->trim().'%');
        }

        if ($request->filled('estimated_arrival_date')) {
            $query->whereDate('estimated_arrival_date', $request->date('estimated_arrival_date'));
        }

        if ($request->filled('box_status')) {
            $query->whereHas('boxes', fn ($boxQuery) => $boxQuery->where('status', trim((string) $request->string('box_status'))));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_code' => ['required', 'string', 'max:100', 'unique:invoices,invoice_code'],
            'po_number' => ['required', 'string', 'max:100', 'unique:invoices,po_number'],
            'target_box_count' => ['required', 'integer', 'min:0'],
            'estimated_arrival_date' => ['nullable', 'date'],
            'manual_entries' => ['required', 'array', 'min:1'],
            'manual_entries.*.box_id' => ['required', 'string', 'max:50'],
            'manual_entries.*.item_name' => ['required', 'string', 'max:150'],
            'manual_entries.*.quantity' => ['required', 'integer', 'min:1'],
            'manual_entries.*.vendor' => ['nullable', 'string', 'max:100'],
            'manual_entries.*.vendor_name' => ['nullable', 'string', 'max:100'],
            'manual_entries.*.vendor_id' => ['nullable', 'integer', 'exists:vendors,vendor_id'],
        ]);

        $entries = $this->extractManualEntries($request, $validated);
        $normalizedEntries = $this->normalizeEntries($request->user(), $entries);

        $invoice = DB::transaction(function () use ($request, $validated, $normalizedEntries) {
            $invoice = Invoice::create([
                ...$validated,
                'status' => 'terverifikasi',
            ]);
            $boxesByCode = [];

            foreach ($normalizedEntries as $entry) {
                $box = $boxesByCode[$entry['box_code']] ?? $this->createOrAttachBox(
                    invoice: $invoice,
                    boxCode: $entry['box_code'],
                    vendorId: $entry['vendor_id']
                );

                $boxesByCode[$entry['box_code']] = $box;

                $box->items()->create([
                    'sku' => $this->generateSku($invoice->invoice_code, $entry['box_code'], $entry['item_name'], $entry['quantity'], $entry['row_number']),
                    'item_name' => $entry['item_name'],
                    'quantity' => $entry['quantity'],
                ]);
            }

            AuditLogger::log($request->user()->user_id, 'CREATE_INVOICE', 'invoices', $invoice->invoice_id, 'Invoice created with box items');

            return $invoice;
        });

        return $this->successResponse(
            $invoice->load(['boxes.vendor', 'boxes.items']),
            'Invoice created successfully',
            201
        );
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->assertInvoiceAccess($request->user(), $invoice);

        return $this->successResponse($invoice->load(['boxes.vendor', 'boxes.items']));
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $this->assertInvoiceAccess($request->user(), $invoice);
        $invoice->delete();

        AuditLogger::log($request->user()->user_id, 'DELETE_INVOICE', 'invoices', $invoice->invoice_id, 'Invoice deleted');

        return $this->successResponse(null, 'Invoice deleted successfully');
    }

    private function createOrAttachBox(Invoice $invoice, string $boxCode, int $vendorId): Box
    {
        $box = Box::withTrashed()->where('box_code', $boxCode)->first();

        if ($box && $box->invoice_id !== null && $box->invoice_id !== $invoice->invoice_id) {
            throw ValidationException::withMessages([
                'manual_entries' => ["Box code {$boxCode} is already assigned to another invoice."],
            ]);
        }

        if ($box && $box->vendor_id !== null && $box->vendor_id !== $vendorId) {
            throw ValidationException::withMessages([
                'manual_entries' => ["Box code {$boxCode} is already assigned to a different vendor."],
            ]);
        }

        if ($box && $box->trashed()) {
            $box->restore();
        }

        if (! $box) {
            return Box::create([
                'box_code' => $boxCode,
                'invoice_id' => $invoice->invoice_id,
                'vendor_id' => $vendorId,
                'status' => 'pending',
                'qr_text' => $this->buildBoxQrText($boxCode),
            ]);
        }

        $box->update([
            'invoice_id' => $invoice->invoice_id,
            'vendor_id' => $vendorId,
            'status' => $box->status ?: 'pending',
            'qr_text' => $box->qr_text ?: $this->buildBoxQrText($boxCode),
        ]);

        return $box->fresh();
    }

    private function extractManualEntries(Request $request, array $validated): array
    {
        $entries = $validated['manual_entries'] ?? $request->input('boxes', $request->input('items', []));

        if (! is_array($entries) || $entries === []) {
            throw ValidationException::withMessages([
                'manual_entries' => ['At least one manual entry row is required.'],
            ]);
        }

        return $entries;
    }

    private function normalizeEntries(User $user, array $entries): array
    {
        $normalizedEntries = [];
        $vendorByBoxCode = [];

        foreach ($entries as $index => $entry) {
            $entryKey = 'manual_entries.'.$index;
            $boxCode = trim((string) ($entry['box_code'] ?? $entry['box_id'] ?? ''));
            $itemName = trim((string) ($entry['item_name'] ?? ''));
            $quantity = $entry['quantity'] ?? null;

            if ($boxCode === '') {
                throw ValidationException::withMessages([
                    $entryKey.'.box_code' => ['BOX ID is required.'],
                ]);
            }

            if ($itemName === '') {
                throw ValidationException::withMessages([
                    $entryKey.'.item_name' => ['Item name is required.'],
                ]);
            }

            if (! is_numeric($quantity) || (int) $quantity < 1) {
                throw ValidationException::withMessages([
                    $entryKey.'.quantity' => ['Quantity must be an integer greater than 0.'],
                ]);
            }

            $vendorId = $this->resolveVendorId($user, $entry, $entryKey);

            if (isset($vendorByBoxCode[$boxCode]) && $vendorByBoxCode[$boxCode] !== $vendorId) {
                throw ValidationException::withMessages([
                    $entryKey.'.vendor' => ['The same BOX ID cannot be submitted with different vendors.'],
                ]);
            }

            $vendorByBoxCode[$boxCode] = $vendorId;
            $normalizedEntries[] = [
                'box_code' => $boxCode,
                'item_name' => $itemName,
                'quantity' => (int) $quantity,
                'vendor_id' => $vendorId,
                'row_number' => $index + 1,
            ];
        }

        return $normalizedEntries;
    }

    private function resolveVendorId(User $user, array $entry, string $entryKey): int
    {
        if ($user->role === 'VENDOR' && $user->vendor_id) {
            return (int) $user->vendor_id;
        }

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

    private function assertInvoiceAccess(User $user, Invoice $invoice): void
    {
        if ($user->role !== 'VENDOR') {
            return;
        }

        $hasOwnedBox = $invoice->boxes()->where('vendor_id', $user->vendor_id)->exists();

        if (! $hasOwnedBox) {
            abort(403, 'Vendor users can only access their own invoice data.');
        }
    }

    private function buildBoxQrText(string $boxCode): string
    {
        return 'BOX:'.$boxCode;
    }

    private function generateSku(string $invoiceCode, string $boxCode, string $itemName, int $quantity, int $rowNumber): string
    {
        $invoiceFragment = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $invoiceCode) ?: 'INV', 0, 24));
        $itemFragment = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $itemName) ?: 'ITEM', 0, 24));

        return sprintf(
            'SKU-%s-%s-%s-%d-%d',
            $invoiceFragment,
            strtoupper($boxCode),
            $itemFragment,
            $quantity,
            $rowNumber
        );
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
