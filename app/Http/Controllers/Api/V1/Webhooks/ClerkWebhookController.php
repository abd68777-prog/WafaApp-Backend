<?php

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Merchant;
use App\Services\AuditLogger;
use App\Services\Clerk\ClerkWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Keeps our copy of Clerk users in step without waiting for them to open an
 * app: a changed primary email, or a user deleted on Clerk's side.
 *
 * Clerk may deliver the same event more than once, so every change here can
 * run twice without effect. A bad signature answers 400, which makes Clerk
 * retry; any event we do not handle answers 200 and is ignored.
 */
class ClerkWebhookController extends Controller
{
    public function __construct(
        private readonly ClerkWebhookSignature $signature,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->signature->isValid($request)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 400);
        }

        $clerkUserId = $request->input('data.id');

        if (is_string($clerkUserId) && $clerkUserId !== '') {
            match ($request->input('type')) {
                'user.updated' => $this->userUpdated($request, $clerkUserId),
                'user.deleted' => $this->userDeleted($request, $clerkUserId),
                default => null,
            };
        }

        return response()->json(['message' => 'Received.']);
    }

    /**
     * The primary email is what a merchant is contacted by and what a
     * dashboard account is listed under.
     */
    private function userUpdated(Request $request, string $clerkUserId): void
    {
        $primaryId = $request->input('data.primary_email_address_id');
        $address = collect($request->input('data.email_addresses', []))->firstWhere('id', $primaryId);
        $email = is_array($address) && is_string($address['email_address'] ?? null)
            ? Str::lower($address['email_address'])
            : null;

        if ($email === null) {
            return;
        }

        Merchant::withTrashed()->where('clerk_user_id', $clerkUserId)->update(['email' => $email]);

        $admin = AdminUser::query()->where('clerk_user_id', $clerkUserId)->first();

        if (! $admin || $admin->email === $email) {
            return;
        }

        // Dashboard emails are unique; one still waiting to link keeps its own.
        $taken = AdminUser::query()->where('email', $email)->whereKeyNot($admin->id)->exists();

        if (! $taken) {
            $before = $admin->email;
            $admin->update(['email' => $email]);

            $this->audit->record(null, 'admin_user.email_synced', $admin, ['email' => $before], ['email' => $email], $request->ip());
        }
    }

    /**
     * A dashboard account is unlinked, so a user recreated with the same email
     * links again on first sign-in. A shop is left untouched — its customers'
     * rewards outlive the sign-in, and closing a shop goes through the
     * dashboard's 30-day deletion — but the trail records that it lost it.
     */
    private function userDeleted(Request $request, string $clerkUserId): void
    {
        $admin = AdminUser::query()->where('clerk_user_id', $clerkUserId)->first();

        if ($admin) {
            $admin->forceFill(['clerk_user_id' => null])->save();

            $this->audit->record(null, 'admin_user.unlinked', $admin, ['clerk_user_id' => $clerkUserId], ['clerk_user_id' => null], $request->ip());
        }

        $merchant = Merchant::query()->where('clerk_user_id', $clerkUserId)->first();

        $alreadyRecorded = $merchant && AuditLog::query()
            ->where('action', 'merchant.clerk_user_deleted')
            ->where('subject_type', $merchant->getMorphClass())
            ->where('subject_id', $merchant->id)
            ->exists();

        if ($merchant && ! $alreadyRecorded) {
            $this->audit->record(null, 'merchant.clerk_user_deleted', $merchant, ['clerk_user_id' => $clerkUserId], null, $request->ip());
        }
    }
}
