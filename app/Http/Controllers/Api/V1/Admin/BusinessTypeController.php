<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SaveLookupRequest;
use App\Http\Resources\AdminLookupResource;
use App\Models\BusinessType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The business types the apps offer (requirements §5.5), managed by admins. Disabled
 * rather than deleted, so shops and cards already using one keep it.
 */
class BusinessTypeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AdminLookupResource::collection(
            BusinessType::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(SaveLookupRequest $request): JsonResponse
    {
        $businessType = BusinessType::query()->create($request->validated());

        return (new AdminLookupResource($businessType->refresh()))->response()->setStatusCode(201);
    }

    public function update(SaveLookupRequest $request, BusinessType $businessType): AdminLookupResource
    {
        $businessType->update($request->validated());

        return new AdminLookupResource($businessType);
    }
}
