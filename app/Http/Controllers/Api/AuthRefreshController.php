<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthRefreshController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return $this->errorResponse('Unauthorized', [], 401);
        }

        try {
            $refreshedToken = auth('api')->setToken($token)->refresh();
            $user = auth('api')->setToken($refreshedToken)->user();
        } catch (\Throwable) {
            return $this->errorResponse('Unauthorized', [], 401);
        }

        if (! $user instanceof User) {
            return $this->errorResponse('Unauthorized', [], 401);
        }

        return $this->successResponse([
            'access_token' => $refreshedToken,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'user_id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'vendor_id' => $user->vendor_id,
                'created_at' => $user->created_at,
            ],
        ], 'Token refreshed successfully');
    }
}
