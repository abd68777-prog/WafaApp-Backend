<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AdminPermission;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminUserResource;
use App\Models\AdminUser;
use Illuminate\Http\Request;

/**
 * Dashboard users sign in and out through Clerk; the role on their
 * `admin_users` row decides what they may do.
 */
class AuthController extends Controller
{
    /**
     * The signed-in account with the permissions of its role, so the dashboard
     * shows only the screens this account may open. The server still checks
     * every permission on its own.
     */
    public function me(Request $request): AdminUserResource
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $admin->forceFill(['last_login_at' => now()])->save();

        return (new AdminUserResource($admin))->additional([
            'permissions' => array_map(
                fn (AdminPermission $permission): string => $permission->value,
                $admin->role->permissions(),
            ),
        ]);
    }
}
