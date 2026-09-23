<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The merchant app is distributed outside the stores, so it is never updated
 * automatically. Over-the-air updates cover JavaScript only; a native change
 * needs a new APK.
 *
 * The server therefore refuses versions older than the configured minimum with
 * a specific code, and the app shows an update screen that cannot be skipped.
 *
 * A request without the header is allowed: browsers and tools do not send it.
 */
class EnsureSupportedAppVersion
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $version = $request->header('X-App-Version');
        $minimum = (string) Setting::read('merchant_min_app_version', '');

        if (blank($version) || blank($minimum) || version_compare($version, $minimum, '>=')) {
            return $next($request);
        }

        return response()->json([
            'message' => 'A newer version of the app is required.',
            'code' => 'app_update_required',
            'minimum_version' => $minimum,
            'download_url' => Setting::read('merchant_app_download_url', ''),
        ], 426);
    }
}
