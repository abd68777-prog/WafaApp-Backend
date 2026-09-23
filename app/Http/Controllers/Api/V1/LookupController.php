<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LookupResource;
use App\Http\Resources\PackageResource;
use App\Models\BusinessType;
use App\Models\Governorate;
use App\Models\Icon;
use App\Models\Package;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The managed lists and public values the apps need before anyone signs in:
 * registration dropdowns, the package matrix, and the policy links shown on
 * the consent screen.
 */
class LookupController extends Controller
{
    public function governorates(): AnonymousResourceCollection
    {
        return LookupResource::collection(
            Governorate::query()->where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    public function businessTypes(): AnonymousResourceCollection
    {
        return LookupResource::collection(
            BusinessType::query()->where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    public function icons(): AnonymousResourceCollection
    {
        return LookupResource::collection(
            Icon::query()->where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    public function packages(): AnonymousResourceCollection
    {
        return PackageResource::collection(
            Package::query()->with('prices')->where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    /**
     * The policy version the consent checkbox must send back, and the links
     * shown next to it.
     */
    public function policy(): JsonResponse
    {
        return response()->json([
            'privacy_policy_version' => Setting::read('privacy_policy_version', '1.2'),
            'privacy_policy_url' => Setting::read('privacy_policy_url', ''),
            'customer_terms_url' => Setting::read('customer_terms_url', ''),
            'merchant_terms_url' => Setting::read('merchant_terms_url', ''),
        ]);
    }
}
