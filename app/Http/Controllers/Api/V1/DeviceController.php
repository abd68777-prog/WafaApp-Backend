<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DevicePlatform;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterDeviceRequest;
use App\Models\Customer;
use App\Models\DeviceToken;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Push notification registration, shared by the customer and merchant apps:
 * each route group resolves its own signed-in user.
 */
class DeviceController extends Controller
{
    /**
     * Sent after signing in and whenever Firebase rotates the token. A token
     * registered to another account moves to this one.
     */
    public function update(RegisterDeviceRequest $request): Response
    {
        DeviceToken::register(
            $request->user(),
            $request->string('token')->value(),
            $request->enum('platform', DevicePlatform::class),
        );

        return response()->noContent();
    }

    /**
     * Sent on sign-out so the device stops receiving this account's
     * notifications. Succeeds even when the token is unknown.
     */
    public function destroy(Request $request, string $token): Response
    {
        /** @var Customer|Merchant $owner */
        $owner = $request->user();

        $owner->deviceTokens()->where('token', $token)->delete();

        return response()->noContent();
    }
}
