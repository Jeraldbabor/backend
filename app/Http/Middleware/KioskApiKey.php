<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * KioskApiKey Middleware
 *
 * Authenticates kiosk devices via a static API key in the X-Kiosk-Api-Key header.
 * The key is stored in .env as KIOSK_API_KEY.
 *
 * This avoids requiring user login on unattended kiosk devices at the school gate.
 */
class KioskApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Kiosk-Api-Key');
        $validKey = config('services.kiosk.api_key');

        if (! $apiKey || ! $validKey || $apiKey !== $validKey) {
            return response()->json([
                'message' => 'Invalid or missing kiosk API key.',
            ], 401);
        }

        return $next($request);
    }
}
