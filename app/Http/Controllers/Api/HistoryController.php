<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoxItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = BoxItem::with([
            'box.locations',
            'box.packages.countingResults',
        ])->orderByDesc('box_item_id');

        if ($user->role === 'VENDOR' && $user->vendor_id) {
            $query->whereHas('box', fn ($boxQuery) => $boxQuery->where('vendor_id', $user->vendor_id));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));

            $query->where(function ($boxItemQuery) use ($search) {
                $boxItemQuery
                    ->where('sku', 'like', '%'.$search.'%')
                    ->orWhere('item_name', 'like', '%'.$search.'%');
            });
        }

        $items = $query->paginate($this->perPage($request));

        return $this->successResponse([
            'items' => $items->getCollection()->map(fn (BoxItem $boxItem) => $this->transformItem($boxItem))->all(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    private function transformItem(BoxItem $boxItem): array
    {
        $boxItem->loadMissing([
            'box.locations',
        ]);

        $latestLocation = $boxItem->box?->locations
            ->sortByDesc('recorded_at')
            ->first();

        $status = strtoupper((string) ($boxItem->box?->status ?? 'pending'));

        return [
            'box_item_id' => $boxItem->box_item_id,
            'sku' => $boxItem->sku,
            'item_name' => $boxItem->item_name,
            'quantity' => (int) $boxItem->quantity,
            'location' => $latestLocation?->location_name,
            'status' => $status,
            'box' => [
                'box_id' => $boxItem->box?->box_id,
                'box_code' => $boxItem->box?->box_code,
                'qr_text' => $boxItem->box?->qr_text,
            ],
            'recorded_at' => $latestLocation?->recorded_at,
        ];
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
