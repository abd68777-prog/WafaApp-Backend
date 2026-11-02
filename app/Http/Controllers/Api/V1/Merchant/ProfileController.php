<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\UpdateProfileRequest;
use App\Http\Requests\Api\V1\Merchant\UploadCoverRequest;
use App\Http\Requests\Api\V1\Merchant\UploadLogoRequest;
use App\Http\Resources\MerchantResource;
use App\Models\Merchant;
use App\Services\Merchant\CoverImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

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

        $disk = config('filesystems.media_disk');
        $path = $request->file('logo')->store('merchants/logos', $disk)
            ?: throw new RuntimeException('The logo could not be stored.');

        $merchant->forceFill(['logo_path' => $path])->save();

        if ($previous !== null) {
            Storage::disk($disk)->delete($previous);
        }

        return $this->profile($merchant);
    }

    /**
     * The cover on the shop's page, cropped to 2:1 and stored as WebP; the
     * old file goes.
     */
    public function cover(UploadCoverRequest $request, CoverImage $image): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();
        $previous = $merchant->cover_path;

        $disk = config('filesystems.media_disk');
        $path = 'merchants/covers/'.Str::uuid7().'.webp';

        Storage::disk($disk)->put($path, $image->fromUpload($request->file('cover')))
            ?: throw new RuntimeException('The cover could not be stored.');

        $merchant->forceFill(['cover_path' => $path])->save();

        if ($previous !== null) {
            Storage::disk($disk)->delete($previous);
        }

        return $this->profile($merchant);
    }

    /**
     * Back to no cover; the app then draws the card color. Removing it
     * again changes nothing.
     */
    public function destroyCover(Request $request): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        $merchant->removeCover();

        return $this->profile($merchant);
    }

    private function profile(Merchant $merchant): JsonResponse
    {
        return (new MerchantResource($merchant->load(['businessType', 'governorate'])))->response();
    }
}
