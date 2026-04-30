<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package as SvsPackage;
use App\Models\QrLog;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrController extends Controller
{
    public function generate(Request $request, SvsPackage $package): JsonResponse
    {
        $this->assertPackageAccess($request->user(), $package);

        $request->validate([
            'allow_regeneration' => ['nullable', 'boolean'],
        ]);

        if ($package->qr_text && ! $request->boolean('allow_regeneration')) {
            return $this->errorResponse('QR text already exists for this package', [
                'package_id' => ['Set allow_regeneration=true to replace the existing QR text.'],
            ], 422);
        }

        $package->loadMissing('box.shipment');

        $qrText = sprintf(
            'SHIP:%s|BOX:%s|PKG:%s',
            $package->box->shipment->shipment_code,
            $package->box->box_code,
            $package->package_code
        );

        $package->update(['qr_text' => $qrText]);

        QrLog::create([
            'package_id' => $package->package_id,
            'user_id' => $request->user()->user_id,
            'qr_text' => $qrText,
        ]);

        AuditLogger::log($request->user()->user_id, 'GENERATE_QR', 'packages', $package->package_id, 'QR generated for package');

        return $this->successResponse([
            'package_id' => $package->package_id,
            'qr_text' => $qrText,
        ], 'QR generated successfully');
    }

    private function assertPackageAccess(User $user, SvsPackage $package): void
    {
        $package->loadMissing('box.shipment');

        if ($user->role === 'VENDOR' && $user->vendor_id !== $package->box->shipment->vendor_id) {
            abort(403, 'Vendor users can only generate QR codes for their own packages.');
        }
    }
}
