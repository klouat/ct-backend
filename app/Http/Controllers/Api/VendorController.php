<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
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
            'items' => $vendors
        ], 'Vendor options loaded successfully');
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
