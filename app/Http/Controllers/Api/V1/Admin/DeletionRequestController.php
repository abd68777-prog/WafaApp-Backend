<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DeletionSubjectType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreDeletionRequestRequest;
use App\Http\Resources\DeletionRequestResource;
use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\DeletionRequest;
use App\Models\Merchant;
use App\Services\AuditLogger;
use App\Services\Customer\CustomerAccountDeleter;
use App\Services\Merchant\MerchantDeletion;
use App\Support\CursorPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Account deletions carried out by the team (requirements §3.6 and §5.3–5.4):
 * a shop leaves after a 30-day waiting period that can be cancelled; a
 * customer who asked by email or through support is deleted at once, as the
 * privacy policy promises.
 */
class DeletionRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'executed', 'cancelled'])],
            'subject_type' => ['sometimes', Rule::enum(DeletionSubjectType::class)],
        ]);

        $requests = DeletionRequest::query()
            ->with('handledBy:id,name')
            ->when(isset($validated['subject_type']), fn ($query) => $query->where('subject_type', $validated['subject_type']))
            ->when(isset($validated['status']), fn ($query) => match ($validated['status']) {
                'pending' => $query->whereNull('executed_at')->whereNull('cancelled_at'),
                'executed' => $query->whereNotNull('executed_at'),
                'cancelled' => $query->whereNotNull('cancelled_at'),
            })
            ->orderByDesc('id');

        return CursorPage::respond($request, $requests, DeletionRequestResource::class, [], function (Collection $page): void {
            $this->nameSubjects($page);
        });
    }

    public function storeForMerchant(StoreDeletionRequestRequest $request, Merchant $merchant, MerchantDeletion $deletion): JsonResponse
    {
        $deletionRequest = $deletion->schedule($merchant, $request->source(), $request->note(), $this->admin($request), $request->ip());

        return $this->respond($deletionRequest, 201);
    }

    public function storeForCustomer(StoreDeletionRequestRequest $request, Customer $customer, CustomerAccountDeleter $deleter, AuditLogger $audit): JsonResponse
    {
        $admin = $this->admin($request);
        $deletionRequest = $deleter->delete($customer, $request->source(), $admin, $request->note());

        $audit->record($admin, 'customer.deleted', $customer, null, [
            'deletion_request_id' => $deletionRequest->id,
            'source' => $request->source()->value,
        ], $request->ip());

        return $this->respond($deletionRequest, 201);
    }

    public function cancel(Request $request, DeletionRequest $deletionRequest, MerchantDeletion $deletion): JsonResponse
    {
        return $this->respond($deletion->cancel($deletionRequest, $this->admin($request), $request->ip()));
    }

    private function respond(DeletionRequest $deletionRequest, int $status = 200): JsonResponse
    {
        $deletionRequest->load('handledBy:id,name');
        $this->nameSubjects(collect([$deletionRequest]));

        return (new DeletionRequestResource($deletionRequest))->response()->setStatusCode($status);
    }

    /**
     * A shop keeps its business name after deletion; a deleted customer has
     * no name left.
     *
     * @param  Collection<int, DeletionRequest>  $requests
     */
    private function nameSubjects(Collection $requests): void
    {
        $merchantIds = $requests->where('subject_type', DeletionSubjectType::Merchant)->pluck('subject_id');
        $names = Merchant::withTrashed()->whereIn('id', $merchantIds)->pluck('business_name', 'id');

        foreach ($requests as $deletionRequest) {
            $deletionRequest->setAttribute('subject_name', $deletionRequest->subject_type === DeletionSubjectType::Merchant
                ? $names->get($deletionRequest->subject_id)
                : null);
        }
    }

    private function admin(Request $request): AdminUser
    {
        return $request->user();
    }
}
