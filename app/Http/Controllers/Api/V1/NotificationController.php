<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Customer;
use App\Models\Merchant;
use App\Support\CursorPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The in-app inbox, shared by the customer and merchant apps: each route
 * group resolves its own signed-in user. Nothing is lost when a push
 * notification was not opened the moment it arrived.
 */
class NotificationController extends Controller
{
    /**
     * Newest first, by cursor. `meta.unread_count` covers the whole inbox, not
     * just the page, so the tab badge is right however far back unread ones go.
     */
    public function index(Request $request): JsonResponse
    {
        $owner = $this->owner($request);

        return CursorPage::respond(
            $request,
            $owner->notifications()->reorder()->orderByDesc('created_at')->orderByDesc('id'),
            NotificationResource::class,
            ['unread_count' => $owner->unreadNotifications()->count()],
        );
    }

    public function read(Request $request, string $notification): Response
    {
        $this->owner($request)->notifications()->findOrFail($notification)->markAsRead();

        return response()->noContent();
    }

    public function readAll(Request $request): Response
    {
        $this->owner($request)->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    private function owner(Request $request): Customer|Merchant
    {
        return $request->user();
    }
}
