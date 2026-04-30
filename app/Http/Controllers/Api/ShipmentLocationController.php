<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\ShipmentLocation;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentLocationController extends Controller
{
    public function index(Request $request, Shipment $shipment): JsonResponse
    {
        $this->assertLocationAccess($request->user(), $shipment);

        $query = ShipmentLocation::where('shipment_id', $shipment->shipment_id)->orderByDesc('recorded_at');

        if ($request->filled('date_from')) {
            $query->where('recorded_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('recorded_at', '<=', $request->input('date_to'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request, Shipment $shipment): JsonResponse
    {
        $this->assertLocationAccess($request->user(), $shipment);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $location = ShipmentLocation::create([
            'shipment_id' => $shipment->shipment_id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
        ]);

        AuditLogger::log($request->user()->user_id, 'STORE_LOCATION', 'shipment_locations', $location->location_id, 'Shipment location recorded');

        return $this->successResponse($location, 'Shipment location recorded successfully', 201);
    }

    public function latest(Request $request, Shipment $shipment): JsonResponse
    {
        $this->assertLocationAccess($request->user(), $shipment);

        $location = $shipment->locations()->latest('recorded_at')->first();

        return $this->successResponse($location, 'Latest shipment location retrieved successfully');
    }

    private function assertLocationAccess(User $user, Shipment $shipment): void
    {
        if ($user->role === 'VENDOR' && $user->vendor_id !== $shipment->vendor_id) {
            abort(403, 'Vendor users can only view their own shipment locations.');
        }

        if ($user->role === 'DRIVER' && $user->vendor_id !== null && $user->vendor_id !== $shipment->vendor_id) {
            abort(403, 'Driver users can only update shipments for their assigned vendor.');
        }
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
