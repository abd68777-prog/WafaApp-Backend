<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\LookupResource;
use App\Models\BusinessType;
use App\Models\Governorate;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * What the customer app reads on start, before anyone signs in: the QR
 * period, the policy version the consent must match, the links, and the lists
 * the directory filters by (contract CustomerConfig).
 */
class ConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'qr_period_seconds' => (int) Setting::read('qr_period_seconds', 60),
                'privacy_policy_version' => Setting::currentPolicyVersion(),
                'privacy_policy_url' => (string) Setting::read('privacy_policy_url', ''),
                'customer_terms_url' => (string) Setting::read('customer_terms_url', ''),
                'governorates' => LookupResource::collection(
                    Governorate::query()->where('is_active', true)->orderBy('sort_order')->get()
                ),
                'business_types' => LookupResource::collection(
                    BusinessType::query()->where('is_active', true)->orderBy('sort_order')->get()
                ),
            ],
        ]);
    }
}
