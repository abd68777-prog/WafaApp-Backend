<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource;
use Illuminate\Http\Request;

/**
 * Admins sign in and out through Clerk on the dashboard; there is no password
 * login here. The `clerk` and `clerk.admin` middleware resolve the admin.
 */
class AuthController extends Controller
{
    public function me(Request $request): AdminResource
    {
        return new AdminResource($request->user());
    }
}
