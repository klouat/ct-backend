<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user')->orderByDesc('audit_id');

        if ($request->filled('username')) {
            $username = trim((string) $request->string('username'));
            $query->whereHas('user', fn ($userQuery) => $userQuery->where('username', 'like', '%'.$username.'%'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->string('action')->trim().'%');
        }

        if ($request->filled('table_name')) {
            $query->where('table_name', $request->string('table_name')->trim());
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search'));

        $query = User::query()
            ->select(['user_id', 'username', 'role'])
            ->orderBy('username');

        if ($search !== '') {
            $query->where('username', 'like', '%'.$search.'%');
        }

        return $this->successResponse(
            $query->limit(10)->get()->map(fn (User $user) => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'role' => $user->role,
            ])->all(),
            'Audit log users loaded successfully'
        );
    }

    public function storeActivity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:100'],
            'table_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $log = AuditLog::create([
            'user_id' => $request->user()?->user_id,
            'action' => $validated['action'],
            'table_name' => $validated['table_name'],
            'description' => $validated['description'] ?? null,
        ]);

        return $this->successResponse($log->load('user'), 'Activity logged successfully', 201);
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }
}
