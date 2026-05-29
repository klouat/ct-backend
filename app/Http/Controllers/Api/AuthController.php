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
        $this->middleware('auth:api', ['except' => ['register', 'login']]);
    }

    /**
     * @unauthenticated
     */
    #[Endpoint(title: 'Register User', description: 'Create a new user account for the SVS API.')]
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:ADMIN,SUPERVISOR,PETUGAS_GUDANG'],
        ]);

        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password_hash' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'vendor_id' => null,
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

        $identifier = trim((string) $credentials['username']);
        $user = User::where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password_hash)) {
            return $this->errorResponse('Invalid credentials', [
                'username' => ['The provided credentials are incorrect.'],
            ], 401);
        }

        $token = auth('api')->login($user);
        AuditLogger::log($user->user_id, 'LOGIN', 'users', $user->user_id, 'User logged in');

        return $this->tokenResponse($token, 'Login successful');
    }

    #[Endpoint(title: 'Logout', description: 'Invalidate the current JWT access token.')]
    public function logout(): JsonResponse
    {
        AuditLogger::log(auth('api')->id(), 'LOGOUT', 'users', auth('api')->id(), 'User logged out');
        auth('api')->logout();

        return $this->successResponse(null, 'Logged out successfully');
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
            'email' => $user->email,
            'role' => $user->role,
            'vendor_id' => $user->vendor_id,
            'created_at' => $user->created_at,
        ];
    }
}
