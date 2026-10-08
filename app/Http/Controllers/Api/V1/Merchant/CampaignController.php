<?php

namespace App\Http\Controllers\Api\V1\Merchant;

use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Merchant\SendCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Jobs\SendCampaign;
use App\Models\Campaign;
use App\Models\Merchant;
use App\Services\Merchant\MerchantState;
use App\Services\Merchant\ShopCustomers;
use App\Support\CursorPage;
use App\Support\LinkDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Campaigns (contract §5.7, requirements §7.3): a title and a text to the
 * shop's customers, a few per week as the package allows — one annoying shop
 * would otherwise push customers to mute every offer and every shop loses.
 */
class CampaignController extends Controller
{
    /**
     * The campaigns sent, newest first, with this week's counter in `meta`.
     */
    public function index(Request $request, MerchantState $state): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();
        $usage = $state->usage($merchant);

        return CursorPage::respond($request, $merchant->campaigns()->orderByDesc('id'), CampaignResource::class, [
            'campaigns_used_this_week' => $usage['campaigns_used_this_week'],
            'weekly_campaigns_limit' => $usage['weekly_campaigns_limit'],
            'campaigns_resets_at' => $usage['campaigns_resets_at'],
        ]);
    }

    public function store(SendCampaignRequest $request, MerchantState $state): JsonResponse
    {
        /** @var Merchant $merchant */
        $merchant = $request->user();

        if (! $merchant->status->canSendCampaigns()) {
            throw ApiException::of(ErrorCode::MerchantStatusBlocksAction, 'The subscription does not allow campaigns now.', [
                'status' => $merchant->status->value,
                'action' => 'campaigns',
            ]);
        }

        foreach (['title', 'body'] as $field) {
            if (LinkDetector::containsLink((string) $request->input($field))) {
                throw ApiException::of(ErrorCode::CampaignContainsLink, 'Links are not allowed in campaigns.', ['field' => $field]);
            }
        }

        // The shop's row is locked while counting, so two taps at once cannot
        // both pass the weekly limit.
        $campaign = DB::transaction(function () use ($request, $merchant, $state): Campaign {
            Merchant::query()->whereKey($merchant->id)->lockForUpdate()->first();
            $usage = $state->usage($merchant);

            if ($usage['campaigns_used_this_week'] >= $usage['weekly_campaigns_limit']) {
                throw ApiException::of(ErrorCode::CampaignWeeklyLimitReached, 'This week\'s campaigns are used up.', [
                    'weekly_limit' => $usage['weekly_campaigns_limit'],
                    'resets_at' => $usage['campaigns_resets_at'],
                ]);
            }

            $campaign = $merchant->campaigns()->create([
                'title' => $request->string('title')->trim()->value(),
                'body' => $request->string('body')->trim()->value(),
            ]);
            $campaign->forceFill([
                'recipients_count' => ShopCustomers::reachableByCampaigns($merchant)->count(),
                'sent_at' => now(),
            ])->save();

            SendCampaign::dispatch($campaign);

            return $campaign;
        });

        return (new CampaignResource($campaign))->response()->setStatusCode(201);
    }
}
