<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Customer\CustomerQrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The seed of the rotating QR code. The app asks once after signing in, keeps
 * it in secure storage and generates codes offline (contract QrSecret).
 */
class QrController extends Controller
{
    public function show(Request $request, CustomerQrCode $qrCode): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return response()->json(['data' => $qrCode->secretFor($customer)]);
    }
}
