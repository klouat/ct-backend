<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\Package as SvsPackage;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SvsPackage::with('box.shipment.vendor')->orderByDesc('package_id');
        $user = $request->user();

        if ($user->role === 'VENDOR' && $user->vendor_id) {
            $query->whereHas('box.shipment', fn ($shipmentQuery) => $shipmentQuery->where('vendor_id', $user->vendor_id));
        }

        if ($request->filled('package_code')) {
            $query->where('package_code', 'like', '%'.$request->string('package_code')->trim().'%');
        }

        if ($request->filled('box_id')) {
            $query->where('box_id', $request->integer('box_id'));
        }

        if ($request->filled('qr_text')) {
            $query->where('qr_text', 'like', '%'.$request->string('qr_text')->trim().'%');
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_code' => ['required', 'string', 'max:50', 'unique:packages,package_code'],
            'box_id' => ['required', 'exists:boxes,box_id'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $box = Box::with('shipment')->findOrFail($validated['box_id']);
        $this->assertBoxAccess($request->user(), $box);

        $package = SvsPackage::create($validated);
        AuditLogger::log($request->user()->user_id, 'CREATE_PACKAGE', 'packages', $package->package_id, 'Package created');

        return $this->successResponse($package->load('box'), 'Package created successfully', 201);
    }

    public function show(Request $request, SvsPackage $package): JsonResponse
    {
        $this->assertBoxAccess($request->user(), $package->box);

        return $this->successResponse($package->load(['box.shipment.vendor', 'qrLogs', 'scanLogs', 'countingResults']));
    }

    public function update(Request $request, SvsPackage $package): JsonResponse
    {
        $validated = $request->validate([
            'package_code' => ['required', 'string', 'max:50', 'unique:packages,package_code,'.$package->package_id.',package_id'],
            'box_id' => ['required', 'exists:boxes,box_id'],
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $box = Box::with('shipment')->findOrFail($validated['box_id']);
        $this->assertBoxAccess($request->user(), $box);

        $package->update($validated);
        AuditLogger::log($request->user()->user_id, 'UPDATE_PACKAGE', 'packages', $package->package_id, 'Package updated');

        return $this->successResponse($package->fresh()->load('box'), 'Package updated successfully');
    }

    public function destroy(Request $request, SvsPackage $package): JsonResponse
    {
        $this->assertBoxAccess($request->user(), $package->box);
        $package->delete();
        AuditLogger::log($request->user()->user_id, 'DELETE_PACKAGE', 'packages', $package->package_id, 'Package deleted');

        return $this->successResponse(null, 'Package deleted successfully');
    }

    private function assertBoxAccess(User $user, Box $box): void
    {
        $box->loadMissing('shipment');

        if ($user->role === 'VENDOR' && $user->vendor_id !== $box->shipment->vendor_id) {
            abort(403, 'Vendor users can only manage their own vendor packages.');
        }
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
