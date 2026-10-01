<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $auth) {}

    /**
     * POST /api/v1/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->auth->register($request->validated());

        $session = $this->auth->login(
            $user->email,
            $request->validated('password'),
            $request->input('device_name', 'web'),
            $request->ip(),
        );

        return $this->created([
            'user' => new UserResource($session['user']),
            'token' => $session['token'],
            'token_expires_at' => $session['expires_at'],
        ], 'Registrasi berhasil.');
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $session = $this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->input('device_name', 'web'),
            $request->ip(),
        );

        return $this->success([
            'user' => new UserResource($session['user']),
            'token' => $session['token'],
            'token_expires_at' => $session['expires_at'],
        ], 'Login berhasil.');
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(
            new UserResource($request->user()->load('shop')),
            'Profil berhasil dimuat.',
        );
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return $this->success(null, 'Logout berhasil.');
    }
}
