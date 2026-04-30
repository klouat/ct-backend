<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\Shipment;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoxController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Box::with('shipment.vendor')->orderByDesc('box_id');
        $user = $request->user();

        if ($user->role === 'VENDOR' && $user->vendor_id) {
            $query->whereHas('shipment', fn ($shipmentQuery) => $shipmentQuery->where('vendor_id', $user->vendor_id));
        }

        if ($request->filled('box_code')) {
            $query->where('box_code', 'like', '%'.$request->string('box_code')->trim().'%');
        }

        if ($request->filled('shipment_id')) {
            $query->where('shipment_id', $request->integer('shipment_id'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'box_code' => ['required', 'string', 'max:50', 'unique:boxes,box_code'],
            'shipment_id' => ['required', 'exists:shipments,shipment_id'],
        ]);

        $shipment = Shipment::findOrFail($validated['shipment_id']);
        $this->assertShipmentAccess($request->user(), $shipment);

        $box = Box::create($validated);
        AuditLogger::log($request->user()->user_id, 'CREATE_BOX', 'boxes', $box->box_id, 'Box created');

        return $this->successResponse($box->load('shipment'), 'Box created successfully', 201);
    }

    public function show(Request $request, Box $box): JsonResponse
    {
        $this->assertShipmentAccess($request->user(), $box->shipment);

        return $this->successResponse($box->load(['shipment.vendor', 'packages']));
    }

    public function update(Request $request, Box $box): JsonResponse
    {
        $validated = $request->validate([
            'box_code' => ['required', 'string', 'max:50', 'unique:boxes,box_code,'.$box->box_id.',box_id'],
            'shipment_id' => ['required', 'exists:shipments,shipment_id'],
        ]);

        $shipment = Shipment::findOrFail($validated['shipment_id']);
        $this->assertShipmentAccess($request->user(), $shipment);

        $box->update($validated);
        AuditLogger::log($request->user()->user_id, 'UPDATE_BOX', 'boxes', $box->box_id, 'Box updated');

        return $this->successResponse($box->fresh()->load('shipment'), 'Box updated successfully');
    }

    public function destroy(Request $request, Box $box): JsonResponse
    {
        $this->assertShipmentAccess($request->user(), $box->shipment);
        $box->delete();
        AuditLogger::log($request->user()->user_id, 'DELETE_BOX', 'boxes', $box->box_id, 'Box deleted');

        return $this->successResponse(null, 'Box deleted successfully');
    }

    private function assertShipmentAccess(User $user, Shipment $shipment): void
    {
        if ($user->role === 'VENDOR' && $user->vendor_id !== $shipment->vendor_id) {
            abort(403, 'Vendor users can only manage their own vendor shipments.');
        }
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
