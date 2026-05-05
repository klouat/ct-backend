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
use Illuminate\Validation\ValidationException;

class BoxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Box::with(['invoice', 'vendor', 'items'])->orderByDesc('box_id');
        $user = $request->user();

        if ($user->role === 'VENDOR' && $user->vendor_id) {
            $query->where('vendor_id', $user->vendor_id);
        }

        if ($request->filled('box_code')) {
            $query->where('box_code', 'like', '%'.$request->string('box_code')->trim().'%');
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->integer('invoice_id'));
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->integer('vendor_id'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'box_code' => ['required', 'string', 'max:50', 'unique:boxes,box_code'],
            'invoice_id' => ['required', 'exists:invoices,invoice_id'],
            'vendor_id' => ['nullable', 'exists:vendors,vendor_id'],
            'vendor_name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:done,pending,match,less,mismatch,over'],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        $vendorId = $this->resolveVendorId($request->user(), $validated);
        $this->assertInvoiceAccess($request->user(), $invoice, $vendorId);

        $box = Box::create([
            'box_code' => $validated['box_code'],
            'invoice_id' => $invoice->invoice_id,
            'vendor_id' => $vendorId,
            'status' => $validated['status'] ?? 'pending',
            'qr_text' => $this->buildBoxQrText($validated['box_code']),
        ]);
        AuditLogger::log($request->user()->user_id, 'CREATE_BOX', 'boxes', $box->box_id, 'Box created');

        return $this->successResponse($box->load(['invoice', 'vendor', 'items']), 'Box created successfully', 201);
    }

    public function show(Request $request, Box $box): JsonResponse
    {
        $this->assertBoxAccess($request->user(), $box);

        return $this->successResponse($box->load(['invoice', 'vendor', 'items', 'packages']));
    }

    public function update(Request $request, Box $box): JsonResponse
    {
        $validated = $request->validate([
            'box_code' => ['required', 'string', 'max:50', 'unique:boxes,box_code,'.$box->box_id.',box_id'],
            'invoice_id' => ['required', 'exists:invoices,invoice_id'],
            'vendor_id' => ['nullable', 'exists:vendors,vendor_id'],
            'vendor_name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:done,pending,match,less,mismatch,over'],
        ]);

        $invoice = Invoice::findOrFail($validated['invoice_id']);
        $vendorId = $this->resolveVendorId($request->user(), $validated);
        $this->assertInvoiceAccess($request->user(), $invoice, $vendorId);

        $box->update([
            'box_code' => $validated['box_code'],
            'invoice_id' => $invoice->invoice_id,
            'vendor_id' => $vendorId,
            'status' => $validated['status'] ?? $box->status ?? 'pending',
            'qr_text' => $box->qr_text ?: $this->buildBoxQrText($validated['box_code']),
        ]);
        AuditLogger::log($request->user()->user_id, 'UPDATE_BOX', 'boxes', $box->box_id, 'Box updated');

        return $this->successResponse($box->fresh()->load(['invoice', 'vendor', 'items']), 'Box updated successfully');
    }

    public function destroy(Request $request, Box $box): JsonResponse
    {
        $this->assertBoxAccess($request->user(), $box);
        $box->delete();
        AuditLogger::log($request->user()->user_id, 'DELETE_BOX', 'boxes', $box->box_id, 'Box deleted');

        return $this->successResponse(null, 'Box deleted successfully');
    }

    private function assertBoxAccess(User $user, Box $box): void
    {
        if ($user->role === 'VENDOR' && $user->vendor_id !== $box->vendor_id) {
            abort(403, 'Vendor users can only manage their own vendor boxes.');
        }
    }

    private function assertInvoiceAccess(User $user, Invoice $invoice, int $vendorId): void
    {
        if ($user->role === 'VENDOR' && $user->vendor_id !== $vendorId) {
            abort(403, 'Vendor users can only manage their own vendor boxes.');
        }

        if ($user->role === 'VENDOR' && $invoice->boxes()->where('vendor_id', '!=', $vendorId)->exists()) {
            throw ValidationException::withMessages([
                'vendor_id' => ['This invoice already contains boxes for a different vendor.'],
            ]);
        }
    }

    private function resolveVendorId(User $user, array $validated): int
    {
        if ($user->role === 'VENDOR' && $user->vendor_id) {
            return (int) $user->vendor_id;
        }

        if (isset($validated['vendor_id'])) {
            return (int) $validated['vendor_id'];
        }

        if (! empty($validated['vendor_name'])) {
            $vendor = Vendor::where('vendor_name', $validated['vendor_name'])->first();

            if ($vendor) {
                return (int) $vendor->vendor_id;
            }
        }

        throw ValidationException::withMessages([
            'vendor' => ['Either vendor_id or vendor_name is required.'],
        ]);
    }

    private function buildBoxQrText(string $boxCode): string
    {
        return 'BOX:'.$boxCode;
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
