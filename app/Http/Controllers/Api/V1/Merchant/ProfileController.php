<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\UpdateProfileRequest;
use App\Http\Requests\Api\V1\Merchant\UploadLogoRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * The shop's own details (contract §5.10). Send only the fields to change;
 * `address: null` clears the address.
 */
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        $merchant->fill($request->validated())->save();

        return $this->profile($merchant);
    }

    /**
     * One logo for the whole shop, shown on every card; the old file goes.
     */
    public function logo(UploadLogoRequest $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();
        $previous = $merchant->logo_path;

        $merchant->forceFill(['logo_path' => $request->file('logo')->store('merchants/logos', 'public')])->save();

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

        return $this->profile($merchant);
    }

    private function profile(Merchant $merchant): JsonResponse
    {
        return (new MerchantResource($merchant->load(['businessType', 'governorate'])))->response();
    }
}
