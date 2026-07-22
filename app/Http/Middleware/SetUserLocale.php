<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the authenticated user's saved panel language (users.locale).
 * Null / unsupported values fall back to Albanian, the platform's primary
 * market. Registered on the operator panel only — the admin panel stays
 * English on purpose.
 */
class SetUserLocale
{
    private const array SUPPORTED = ['sq', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'sq');

        return $next($request);
    }
}
