<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CountingResult;
use App\Models\Package as SvsPackage;
use App\Support\AuditLogger;
use App\Support\VerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CountingController extends Controller
{
    public function store(Request $request, SvsPackage $package): JsonResponse
    {
        $validated = $request->validate([
            'counted_qty' => ['required', 'integer', 'min:0'],
        ]);

        $count = CountingResult::create([
            'package_id' => $package->package_id,
            'counted_qty' => $validated['counted_qty'],
        ]);

        AuditLogger::log($request->user()->user_id, 'COUNT_PACKAGE', 'counting_results', $count->counting_id, 'Package counted');

        return $this->successResponse([
            'count' => $count,
            'verification' => VerificationService::package($package->fresh()),
        ], 'Package counted successfully', 201);
    }
}
