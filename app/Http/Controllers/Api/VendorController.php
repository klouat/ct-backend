<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::query()->orderBy('vendor_id');

        if ($request->filled('vendor_name')) {
            $query->where('vendor_name', 'like', '%'.$request->string('vendor_name')->trim().'%');
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function publicList(Request $request): JsonResponse
    {
        $vendors = Vendor::query()
            ->select('vendor_id', 'vendor_name')
            ->orderBy('vendor_name')
            ->get();

        return $this->successResponse([
            'items' => $vendors,
        ], 'Vendor options loaded successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_name' => ['required', 'string', 'max:150', 'unique:vendors,vendor_name'],
        ]);

        $vendor = Vendor::create(['vendor_name' => trim($validated['vendor_name'])]);

        AuditLogger::log(
            $request->user()?->user_id,
            'CREATE_VENDOR',
            'vendors',
            $vendor->vendor_id,
            "Vendor '{$vendor->vendor_name}' created"
        );

        return $this->successResponse($vendor, 'Vendor created successfully', 201);
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $validated = $request->validate([
            'vendor_name' => [
                'required',
                'string',
                'max:150',
                \Illuminate\Validation\Rule::unique('vendors', 'vendor_name')->ignore($vendor->vendor_id, 'vendor_id'),
            ],
        ]);

        $old_name = $vendor->vendor_name;
        $vendor->update(['vendor_name' => trim($validated['vendor_name'])]);

        AuditLogger::log(
            $request->user()?->user_id,
            'UPDATE_VENDOR',
            'vendors',
            $vendor->vendor_id,
            "Vendor renamed from '{$old_name}' to '{$vendor->vendor_name}'"
        );

        return $this->successResponse($vendor->fresh(), 'Vendor updated successfully');
    }

    public function destroy(Request $request, Vendor $vendor): JsonResponse
    {
        $vendor_name = $vendor->vendor_name;
        $vendor_id   = $vendor->vendor_id;

        $vendor->delete();

        AuditLogger::log(
            $request->user()?->user_id,
            'DELETE_VENDOR',
            'vendors',
            $vendor_id,
            "Vendor '{$vendor_name}' deleted"
        );

        return $this->successResponse(null, 'Vendor deleted successfully');
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
