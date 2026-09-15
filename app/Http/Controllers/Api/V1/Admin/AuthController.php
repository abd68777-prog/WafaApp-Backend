<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Token authentication for the admin dashboard (Next.js).
 *
 * There is no public registration: the platform admin account is created by
 * the AdminSeeder. Customer (OTP) and merchant authentication get their own
 * controllers.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $admin = Admin::query()->where('email', $request->string('email')->value())->first();

        if (! $admin || ! Hash::check($request->string('password')->value(), $admin->password)) {
            // Same message either way so the endpoint cannot be used to
            // discover which email addresses are registered.
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $admin->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'data' => new AdminResource($admin),
            'token' => $admin->createToken($request->string('device_name', 'dashboard')->value())->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request): AdminResource
    {
        return new AdminResource($request->user());
    }

    /**
     * Revoke only the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Revoke every token of the admin (sign out on all devices).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out on all devices.']);
    }
}
