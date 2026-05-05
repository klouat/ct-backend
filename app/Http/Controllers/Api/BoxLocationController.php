<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Box;
use App\Models\BoxLocation;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoxLocationController extends Controller
{
    public function index(Request $request, Box $box): JsonResponse
    {
        $this->assertLocationAccess($request->user(), $box);

        $query = BoxLocation::where('box_id', $box->box_id)->orderByDesc('recorded_at');

        if ($request->filled('date_from')) {
            $query->where('recorded_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('recorded_at', '<=', $request->input('date_to'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request, Box $box): JsonResponse
    {
        $this->assertLocationAccess($request->user(), $box);

        $validated = $request->validate([
            'location_name' => ['required', 'string', 'max:150'],
        ]);

        $location = BoxLocation::create([
            'box_id' => $box->box_id,
            'location_name' => $validated['location_name'],
        ]);

        AuditLogger::log($request->user()->user_id, 'STORE_BOX_LOCATION', 'box_locations', $location->box_location_id, 'Box location recorded');

        return $this->successResponse($location, 'Box location recorded successfully', 201);
    }

    public function latest(Request $request, Box $box): JsonResponse
    {
        $this->assertLocationAccess($request->user(), $box);

        $location = $box->locations()->latest('recorded_at')->first();

        return $this->successResponse($location, 'Latest box location retrieved successfully');
    }

    private function assertLocationAccess(User $user, Box $box): void
    {
        if ($user->role === 'VENDOR' && $user->vendor_id !== $box->vendor_id) {
            abort(403, 'Vendor users can only view their own box locations.');
        }

        if ($user->role === 'DRIVER' && $user->vendor_id !== null && $user->vendor_id !== $box->vendor_id) {
            abort(403, 'Driver users can only update boxes for their assigned vendor.');
        }
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
