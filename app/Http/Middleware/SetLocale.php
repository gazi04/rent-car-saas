<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** @var list<string> */
    private const SUPPORTED = ['sq', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale')
            ?? tenant()?->setting('default_locale', config('branding.defaults.default_locale'))
            ?? 'sq';

        if (in_array($locale, self::SUPPORTED, strict: true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
