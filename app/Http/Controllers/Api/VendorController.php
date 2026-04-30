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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vendor_name' => ['required', 'string', 'max:100'],
        ]);

        $vendor = Vendor::create($validated);
        AuditLogger::log($request->user()->user_id, 'CREATE_VENDOR', 'vendors', $vendor->vendor_id, 'Vendor created');

        return $this->successResponse($vendor, 'Vendor created successfully', 201);
    }

    public function show(Vendor $vendor): JsonResponse
    {
        return $this->successResponse($vendor);
    }

    public function update(Request $request, Vendor $vendor): JsonResponse
    {
        $validated = $request->validate([
            'vendor_name' => ['required', 'string', 'max:100'],
        ]);

        $vendor->update($validated);
        AuditLogger::log($request->user()->user_id, 'UPDATE_VENDOR', 'vendors', $vendor->vendor_id, 'Vendor updated');

        return $this->successResponse($vendor, 'Vendor updated successfully');
    }

    public function destroy(Request $request, Vendor $vendor): JsonResponse
    {
        $vendor->delete();
        AuditLogger::log($request->user()->user_id, 'DELETE_VENDOR', 'vendors', $vendor->vendor_id, 'Vendor deleted');

        return $this->successResponse(null, 'Vendor deleted successfully');
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
