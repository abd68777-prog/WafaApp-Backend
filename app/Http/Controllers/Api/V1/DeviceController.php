<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DevicePlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

/**
 * Push notification registration, shared by the customer and merchant apps:
 * each route group resolves its own signed-in user.
 *
 * The app calls this after signing in and whenever Firebase rotates the token.
 */
class DeviceController extends Controller
{
    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        DeviceToken::register(
            $request->user(),
            $request->string('token')->value(),
            $request->enum('platform', DevicePlatform::class),
        );

        return response()->json(['message' => 'Device registered.']);
    }
}
