<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user')->orderByDesc('audit_id');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->string('action')->trim().'%');
        }

        if ($request->filled('table_name')) {
            $query->where('table_name', $request->string('table_name')->trim());
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function storeActivity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:100'],
            'table_name' => ['required', 'string', 'max:100'],
            'record_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $log = AuditLog::create([
            'user_id' => $request->user()?->user_id,
            'action' => $validated['action'],
            'table_name' => $validated['table_name'],
            'record_id' => $validated['record_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return $this->successResponse($log->load('user'), 'Activity logged successfully', 201);
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
