<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SaveLookupRequest;
use App\Http\Resources\AdminLookupResource;
use App\Models\Icon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The icons the apps offer (requirements §5.5), managed by admins. Disabled
 * rather than deleted, so shops and cards already using one keep it.
 */
class IconController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AdminLookupResource::collection(
            Icon::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(SaveLookupRequest $request): JsonResponse
    {
        $icon = Icon::query()->create($request->validated());

        return (new AdminLookupResource($icon->refresh()))->response()->setStatusCode(201);
    }

    public function update(SaveLookupRequest $request, Icon $icon): AdminLookupResource
    {
        $icon->update($request->validated());

        return new AdminLookupResource($icon);
    }
}
