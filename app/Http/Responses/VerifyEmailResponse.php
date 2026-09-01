<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Post-email-verification redirect.
 *
 * The verification link is always opened on the central host, so Fortify's
 * default would drop every user on the central marketing home ("/"). Route by
 * role instead: an operator/staff account belongs on its own tenant subdomain
 * (the Filament operator panel), and only once the tenant is active — while it
 * is still awaiting admin approval (or is suspended) there is nowhere for them
 * to land, so send them to the central login with an explanatory message.
 *
 * Everyone else (Super Admins) keeps Fortify's default behavior.
 */
class VerifyEmailResponse implements VerifyEmailResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        $user = $request->user();

        if ($user instanceof User && in_array($user->role, ['operator', 'staff'], true) && $user->tenant_id !== null) {
            $tenant = Tenant::query()->whereKey($user->tenant_id)->first();

            if ($tenant !== null && $tenant->isActive() && ($root = $tenant->publicRootUrl()) !== null) {
                return redirect()->away($root.'/dashboard');
            }

            $request->session()->flash('status', __(
                'Your email is verified. An administrator will review your account shortly — '.
                'you will be able to sign in at your subdomain once it is approved.'
            ));

            return redirect()->route('login');
        }

        return redirect()->intended(Fortify::redirects('email-verification').'?verified=1');
    }
}
