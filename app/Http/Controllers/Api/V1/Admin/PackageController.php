<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\SavePackageRequest;
use App\Http\Resources\AdminPackageResource;
use App\Models\AdminUser;
use App\Models\Package;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Packages and their price matrix, set from the dashboard by the Super Admin
 * (requirements §5.5): the payments reviewer never edits a price.
 *
 * A price change reaches the next payment only — a payment keeps the price it
 * was uploaded with. A limit change reaches every shop on the package at
 * once, since the limits are read from the package. Packages are never
 * deleted, only deactivated, so past periods keep pointing at them.
 */
class PackageController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return AdminPackageResource::collection(
            Package::query()->with('prices')->orderBy('sort_order')->orderBy('id')->get()
        )->response();
    }

    public function store(SavePackageRequest $request): JsonResponse
    {
        $package = DB::transaction(function () use ($request): Package {
            $package = Package::query()->create($request->safe()->except('prices'));
            $this->savePrices($package, $request->validated('prices'));

            $this->audit->record($this->admin($request), 'package.created', $package, null, $this->snapshot($package->refresh()), $request->ip());

            return $package;
        });

        return (new AdminPackageResource($package->load('prices')))->response()->setStatusCode(201);
    }

    public function update(SavePackageRequest $request, Package $package): JsonResponse
    {
        DB::transaction(function () use ($request, $package): void {
            $before = $this->snapshot($package);

            $package->update($request->safe()->except('prices'));
            $this->savePrices($package, $request->validated('prices', []));

            $this->audit->record($this->admin($request), 'package.updated', $package, $before, $this->snapshot($package->refresh()), $request->ip());
        });

        return (new AdminPackageResource($package->load('prices')))->response();
    }

    /**
     * @param  list<array{duration_months: int, price_usd: float|string|null}>  $prices
     */
    private function savePrices(Package $package, array $prices): void
    {
        foreach ($prices as $price) {
            if ($price['price_usd'] === null) {
                $package->prices()->where('duration_months', $price['duration_months'])->delete();

                continue;
            }

            $package->prices()->updateOrCreate(
                ['duration_months' => $price['duration_months']],
                ['price_usd' => $price['price_usd']],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Package $package): array
    {
        return [
            ...$package->only(['name', 'cards_limit', 'weekly_campaigns_limit', 'is_active', 'sort_order']),
            'prices' => $package->prices()->orderBy('duration_months')->pluck('price_usd', 'duration_months')
                ->map(fn ($price): string => (string) $price)
                ->all(),
        ];
    }

    private function admin(SavePackageRequest $request): AdminUser
    {
        return $request->user();
    }
}
