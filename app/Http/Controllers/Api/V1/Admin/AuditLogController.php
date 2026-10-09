<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\CursorPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The audit trail (requirements §5.1), newest first, for the Super Admin:
 * every payment decision, manual extension, cancelled stamp, identity or
 * birthdate edit, revealed phone number, suspension and settings change.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['sometimes', 'string', 'max:64'],
            'admin_user_id' => ['sometimes', 'integer'],
            'subject_type' => ['required_with:subject_id', 'string', 'max:32'],
            'subject_id' => ['sometimes', 'integer'],
        ]);

        $logs = AuditLog::query()
            ->with('adminUser:id,name')
            ->when(isset($validated['action']), fn ($query) => $query->where('action', $validated['action']))
            ->when(isset($validated['admin_user_id']), fn ($query) => $query->where('admin_user_id', $validated['admin_user_id']))
            ->when(isset($validated['subject_type']), fn ($query) => $query->where('subject_type', $validated['subject_type']))
            ->when(isset($validated['subject_id']), fn ($query) => $query->where('subject_id', $validated['subject_id']))
            ->orderByDesc('id');

        return CursorPage::respond($request, $logs, AuditLogResource::class);
    }
}
