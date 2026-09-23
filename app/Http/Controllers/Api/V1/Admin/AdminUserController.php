<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAdminUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\AdminUser;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Dashboard accounts, managed by super admins only (requirements §5.1).
 *
 * "Deleting" an account deactivates it: the row stays so the audit trail keeps
 * saying who approved a payment or cancelled a stamp.
 */
class AdminUserController extends Controller
{
    /**
     * The fields worth recording before and after a change.
     */
    private const AUDITED = ['name', 'email', 'role', 'is_active'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): AnonymousResourceCollection
    {
        return AdminUserResource::collection(
            AdminUser::query()->orderByDesc('is_active')->orderBy('name')->get()
        );
    }

    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $adminUser = AdminUser::query()->create([...$request->validated(), 'is_active' => true]);

        $this->audit->record(
            $request->user(),
            'admin_user.created',
            $adminUser,
            after: $adminUser->only(self::AUDITED),
            ipAddress: $request->ip(),
        );

        return (new AdminUserResource($adminUser))->response()->setStatusCode(201);
    }

    public function update(UpdateAdminUserRequest $request, AdminUser $adminUser): AdminUserResource
    {
        $this->guardAgainstLockout(
            $request,
            $adminUser,
            $request->has('role') ? $request->enum('role', AdminRole::class) : $adminUser->role,
            $request->has('is_active') ? $request->boolean('is_active') : $adminUser->is_active,
        );

        $before = $adminUser->only(self::AUDITED);
        $adminUser->update($request->validated());

        if ($adminUser->wasChanged()) {
            $changed = array_keys($adminUser->getChanges());

            $this->audit->record(
                $request->user(),
                'admin_user.updated',
                $adminUser,
                before: array_intersect_key($before, array_flip($changed)),
                after: $adminUser->only(array_intersect(self::AUDITED, $changed)),
                ipAddress: $request->ip(),
            );
        }

        return new AdminUserResource($adminUser);
    }

    /**
     * Deactivate the account. It can be switched back on with an update.
     */
    public function destroy(Request $request, AdminUser $adminUser): AdminUserResource
    {
        $this->guardAgainstLockout($request, $adminUser, $adminUser->role, false);

        if ($adminUser->is_active) {
            $adminUser->update(['is_active' => false]);

            $this->audit->record(
                $request->user(),
                'admin_user.deactivated',
                $adminUser,
                before: ['is_active' => true],
                after: ['is_active' => false],
                ipAddress: $request->ip(),
            );
        }

        return new AdminUserResource($adminUser);
    }

    /**
     * Nobody changes their own role or switches themselves off. Only an active
     * super admin gets here, so this alone guarantees one always remains to
     * manage accounts, prices and settings.
     *
     * @throws ValidationException
     */
    private function guardAgainstLockout(Request $request, AdminUser $target, AdminRole $newRole, bool $willBeActive): void
    {
        if ($target->is($request->user()) && ($newRole !== $target->role || ! $willBeActive)) {
            throw ValidationException::withMessages([
                'admin_user' => ['You cannot change the role of your own account or deactivate it.'],
            ]);
        }
    }
}
