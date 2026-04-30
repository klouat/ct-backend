<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['register', 'login', 'refresh']]);
    }

    /**
     * @unauthenticated
     */
    #[Endpoint(title: 'Register User', description: 'Create a new user account for the SVS API.')]
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:ADMIN,OPERATOR,VENDOR,DRIVER'],
            'vendor_id' => ['nullable', 'exists:vendors,vendor_id'],
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'password_hash' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'vendor_id' => $validated['vendor_id'] ?? null,
        ]);

        AuditLogger::log(
            auth('api')->id(),
            'REGISTER_USER',
            'users',
            $user->user_id,
            sprintf('User %s registered with role %s', $user->username, $user->role)
        );

        return $this->successResponse([
            'user' => $this->transformUser($user),
        ], 'User registered successfully', 201);
    }

    /**
     * @unauthenticated
     */
    #[Endpoint(title: 'Login', description: 'Authenticate a user and receive a JWT access token.')]
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! $token = auth('api')->attempt($credentials)) {
            return $this->errorResponse('Invalid credentials', [
                'username' => ['The provided credentials are incorrect.'],
            ], 401);
        }

        return $this->tokenResponse($token, 'Login successful');
    }

    #[Endpoint(title: 'Current User', description: 'Get the currently authenticated user.')]
    public function me(): JsonResponse
    {
        return $this->successResponse($this->transformUser(auth('api')->user()), 'Current user retrieved successfully');
    }

    #[Endpoint(title: 'Logout', description: 'Invalidate the current JWT access token.')]
    public function logout(): JsonResponse
    {
        AuditLogger::log(auth('api')->id(), 'LOGOUT', 'users', auth('api')->id(), 'User logged out');
        auth('api')->logout();

        return $this->successResponse(null, 'Logged out successfully');
    }

    /**
     * @unauthenticated
     */
    #[Endpoint(title: 'Refresh Token', description: 'Refresh a JWT access token and return a new token payload.')]
    public function refresh(): JsonResponse
    {
        return $this->tokenResponse(auth('api')->refresh(), 'Token refreshed successfully');
    }

    private function tokenResponse(string $token, string $message): JsonResponse
    {
        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->transformUser(auth('api')->user()),
        ], $message);
    }

    private function transformUser(User $user): array
    {
        return [
            'user_id' => $user->user_id,
            'username' => $user->username,
            'role' => $user->role,
            'vendor_id' => $user->vendor_id,
            'created_at' => $user->created_at,
        ];
    }
}
