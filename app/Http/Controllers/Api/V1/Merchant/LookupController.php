<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\LookupResource;
use App\Http\Resources\PackageResource;
use App\Models\BusinessType;
use App\Models\Governorate;
use App\Models\Icon;
use App\Models\Package;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * Everything the merchant app's forms need (contract MerchantLookups): the
 * managed lists, the package matrix, where to pay, and the input limits the
 * app builds its validation from. Open before registration is complete.
 */
class LookupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'governorates' => LookupResource::collection(
                    Governorate::query()->where('is_active', true)->orderBy('sort_order')->get()
                ),
                'business_types' => LookupResource::collection(
                    BusinessType::query()->where('is_active', true)->orderBy('sort_order')->get()
                ),
                'icons' => LookupResource::collection(
                    Icon::query()->where('is_active', true)->orderBy('sort_order')->get()
                ),
                'packages' => PackageResource::collection(
                    Package::query()->with('prices')->where('is_active', true)->orderBy('sort_order')->get()
                ),
                'payment' => [
                    'exchange_rate_syp' => number_format((float) Setting::read('exchange_rate_syp', 0), 2, '.', ''),
                    'syriatel_cash_number' => (string) Setting::read('syriatel_cash_number', ''),
                    'bank_transfer_details' => (string) Setting::read('bank_transfer_details', ''),
                    'review_sla_hours' => (int) Setting::read('payment_review_sla_hours', 24),
                ],
                'limits' => [
                    'card_stamps_min' => (int) Setting::read('card_stamps_min', 3),
                    'card_stamps_max' => (int) Setting::read('card_stamps_max', 10),
                    'campaign_title_max' => (int) Setting::read('campaign_title_max', 60),
                    'campaign_body_max' => (int) Setting::read('campaign_body_max', 300),
                    'stamp_interval_minutes' => (int) Setting::read('stamp_interval_minutes', 60),
                    'pin_unlock_hours' => (int) Setting::read('pin_unlock_hours', 12),
                ],
                'links' => [
                    'privacy_policy_url' => (string) Setting::read('privacy_policy_url', ''),
                    'merchant_terms_url' => (string) Setting::read('merchant_terms_url', ''),
                ],
            ],
        ]);
    }
}
