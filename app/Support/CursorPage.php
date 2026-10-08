<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A page of a long list, the way the apps page (contract §1.6): by cursor,
 * so items arriving at the top while the user scrolls are neither repeated
 * nor skipped.
 *
 *     { "data": [ … ], "meta": { "next_cursor": "…" | null } }
 */
final class CursorPage
{
    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 50;

    /**
     * @param  class-string<JsonResource>  $resource
     * @param  array<string, mixed>  $meta  Extra fields next to `next_cursor`.
     */
    public static function respond(Request $request, Builder $query, string $resource, array $meta = []): JsonResponse
    {
        $validated = $request->validate([
            'cursor' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'between:1,'.self::MAX_LIMIT],
        ]);

        $page = $query->cursorPaginate((int) ($validated['limit'] ?? self::DEFAULT_LIMIT));

        return response()->json([
            'data' => $resource::collection($page->getCollection()),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode(), ...$meta],
        ]);
    }
}
