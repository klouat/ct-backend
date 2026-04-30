<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Shipment::with('vendor')->orderByDesc('shipment_id');

        if ($user->role === 'VENDOR' && $user->vendor_id) {
            $query->where('vendor_id', $user->vendor_id);
        }

        foreach (['shipment_code', 'vendor_id', 'status', 'shipment_date'] as $filter) {
            if ($request->filled($filter)) {
                $operator = $filter === 'shipment_code' ? 'like' : '=';
                $value = $filter === 'shipment_code'
                    ? '%'.$request->string($filter)->trim().'%'
                    : $request->input($filter);
                $query->where($filter, $operator, $value);
            }
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'shipment_code' => ['required', 'string', 'max:50', 'unique:shipments,shipment_code'],
            'vendor_id' => ['required', 'exists:vendors,vendor_id'],
            'shipment_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $this->assertVendorOwnership($user, (int) $validated['vendor_id']);

        $shipment = Shipment::create([
            ...$validated,
            'status' => $validated['status'] ?? 'PENDING',
        ]);

        AuditLogger::log($user->user_id, 'CREATE_SHIPMENT', 'shipments', $shipment->shipment_id, 'Shipment created');

        return $this->successResponse($shipment->load('vendor'), 'Shipment created successfully', 201);
    }

    public function show(Request $request, Shipment $shipment): JsonResponse
    {
        $this->assertShipmentAccess($request->user(), $shipment);

        return $this->successResponse($shipment->load(['vendor', 'boxes']));
    }

    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        $user = $request->user();
        $this->assertShipmentAccess($user, $shipment);

        $validated = $request->validate([
            'shipment_code' => ['required', 'string', 'max:50', 'unique:shipments,shipment_code,'.$shipment->shipment_id.',shipment_id'],
            'vendor_id' => ['required', 'exists:vendors,vendor_id'],
            'shipment_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $this->assertVendorOwnership($user, (int) $validated['vendor_id']);

        $shipment->update($validated);
        AuditLogger::log($user->user_id, 'UPDATE_SHIPMENT', 'shipments', $shipment->shipment_id, 'Shipment updated');

        return $this->successResponse($shipment->fresh()->load('vendor'), 'Shipment updated successfully');
    }

    public function destroy(Request $request, Shipment $shipment): JsonResponse
    {
        $this->assertShipmentAccess($request->user(), $shipment);
        $shipment->delete();
        AuditLogger::log($request->user()->user_id, 'DELETE_SHIPMENT', 'shipments', $shipment->shipment_id, 'Shipment deleted');

        return $this->successResponse(null, 'Shipment deleted successfully');
    }

    private function assertShipmentAccess(User $user, Shipment $shipment): void
    {
        if ($user->role === 'VENDOR' && $user->vendor_id !== $shipment->vendor_id) {
            abort(403, 'You do not have permission to access this shipment.');
        }
    }

    private function assertVendorOwnership(User $user, int $vendorId): void
    {
        if ($user->role === 'VENDOR' && $user->vendor_id !== $vendorId) {
            abort(403, 'Vendor users can only manage their own vendor data.');
        }
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
