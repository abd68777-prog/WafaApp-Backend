<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\ResolveScanRequest;
use App\Models\Merchant;
use App\Services\Stamping\ScanPreview;
use Illuminate\Http\JsonResponse;

/**
 * Step 2 of the scan sequence (contract §5.3): who was scanned, and which
 * button the confirmation screen shows. Open to the cashier, without the PIN.
 */
class ScanController extends Controller
{
    public function resolve(ResolveScanRequest $request, ScanPreview $preview): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        return response()->json([
            'data' => $preview->resolve(
                $merchant,
                $request->integer('card_id'),
                $request->filled('qr') ? $request->string('qr')->value() : null,
                $request->phoneE164(),
            ),
        ]);
    }
}
