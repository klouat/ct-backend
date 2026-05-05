<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\Invoice;
use App\Models\Package as SvsPackage;
use App\Models\Shipment;
use App\Models\User;
use App\Support\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function package(Request $request, SvsPackage $package): JsonResponse
    {
        $this->assertPackageAccess($request->user(), $package);

        return $this->successResponse(VerificationService::package($package), 'Package verification retrieved successfully');
    }

    public function box(Request $request, Box $box): JsonResponse
    {
        $this->assertVendorBoxAccess($request->user(), $box);

        return $this->successResponse(VerificationService::box($box), 'Box verification retrieved successfully');
    }

    public function invoice(Request $request, Invoice $invoice): JsonResponse
    {
        $this->assertVendorInvoiceAccess($request->user(), $invoice);

        return $this->successResponse(VerificationService::invoice($invoice), 'Invoice verification retrieved successfully');
    }

    public function shipment(Request $request, Shipment $shipment): JsonResponse
    {
        $this->assertVendorShipmentAccess($request->user(), $shipment);

        return $this->successResponse(VerificationService::shipment($shipment), 'Shipment verification retrieved successfully');
    }

    private function assertPackageAccess(User $user, SvsPackage $package): void
    {
        $package->loadMissing('box.vendor');
        $this->assertVendorBoxAccess($user, $package->box);
    }

    private function assertVendorShipmentAccess(User $user, Shipment $shipment): void
    {
        if ($user->role === 'VENDOR' && $user->vendor_id !== $shipment->vendor_id) {
            abort(403, 'Vendor users can only access their own shipment data.');
        }
    }

    private function assertVendorBoxAccess(User $user, Box $box): void
    {
        if ($user->role === 'VENDOR' && $user->vendor_id !== $box->vendor_id) {
            abort(403, 'Vendor users can only access their own box data.');
        }
    }

    private function assertVendorInvoiceAccess(User $user, Invoice $invoice): void
    {
        if ($user->role !== 'VENDOR') {
            return;
        }

        $hasOwnedBox = $invoice->boxes()->where('vendor_id', $user->vendor_id)->exists();

        if (! $hasOwnedBox) {
            abort(403, 'Vendor users can only access their own invoice data.');
        }
    }
}
