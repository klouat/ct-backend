<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->with('vendor')
            ->orderBy('user_id');

        $search = trim((string) $request->string('search'));

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('username', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('role')) {
            $query->where('role', trim((string) $request->string('role')));
        }

        return $this->paginatedResponse(
            $query->paginate($this->perPage($request))->through(
                fn (User $user): array => $this->transformUser($user)
            ),
            'Users loaded successfully'
        );
    }

    public function show(User $user): JsonResponse
    {
        return $this->successResponse(
            $this->transformUser($user->load('vendor')),
            'User loaded successfully'
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'username' => trim($validated['username']),
            'email' => trim($validated['email']),
            'password_hash' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'vendor_id' => $validated['role'] === 'VENDOR' ? $validated['vendor_id'] : null,
        ]);

        AuditLogger::log(
            $request->user()?->user_id,
            'CREATE_USER',
            'users',
            $user->user_id,
            sprintf('User %s created with role %s', $user->username, $user->role)
        );

        return $this->successResponse(
            $this->transformUser($user->load('vendor')),
            'User created successfully',
            201
        );
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        if ($user->role === 'ADMIN' && $validated['role'] !== 'ADMIN' && ! $this->hasAnotherAdmin($user)) {
            return $this->errorResponse(
                'At least one administrator account must remain active.',
                ['role' => ['At least one administrator account must remain active.']],
                422
            );
        }

        $user->fill([
            'username' => trim($validated['username']),
            'email' => trim($validated['email']),
            'role' => $validated['role'],
            'vendor_id' => $validated['role'] === 'VENDOR' ? $validated['vendor_id'] : null,
        ]);

        if (filled($validated['password'] ?? null)) {
            $user->password_hash = Hash::make($validated['password']);
        }

        $user->save();

        AuditLogger::log(
            $request->user()?->user_id,
            'UPDATE_USER',
            'users',
            $user->user_id,
            sprintf('User %s updated', $user->username)
        );

        return $this->successResponse(
            $this->transformUser($user->fresh()->load('vendor')),
            'User updated successfully'
        );
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($actor?->user_id === $user->user_id) {
            return $this->errorResponse(
                'You cannot delete your own account.',
                ['user' => ['You cannot delete your own account.']],
                422
            );
        }

        if ($user->role === 'ADMIN' && ! $this->hasAnotherAdmin($user)) {
            return $this->errorResponse(
                'At least one administrator account must remain active.',
                ['user' => ['At least one administrator account must remain active.']],
                422
            );
        }

        $deletedUserId = $user->user_id;
        $deletedUsername = $user->username;

        $user->delete();

        AuditLogger::log(
            $actor?->user_id,
            'DELETE_USER',
            'users',
            $deletedUserId,
            sprintf('User %s deleted', $deletedUsername)
        );

        return $this->successResponse(null, 'User deleted successfully');
    }

    private function perPage(Request $request): int
    {
        return min((int) $request->integer('per_page', 20), 100);
    }

    private function hasAnotherAdmin(User $user): bool
    {
        return User::query()
            ->where('role', 'ADMIN')
            ->where('user_id', '!=', $user->user_id)
            ->exists();
    }

    private function transformUser(User $user): array
    {
        return [
            'user_id' => $user->user_id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'vendor_id' => $user->vendor_id,
            'vendor_name' => $user->vendor?->vendor_name,
            'created_at' => $user->created_at,
        ];
    }
}
