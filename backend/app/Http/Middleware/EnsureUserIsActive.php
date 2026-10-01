<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks suspended accounts.
 *
 * Checked on every authenticated request rather than only at login, so
 * suspending a user takes effect immediately instead of when their existing
 * token happens to expire.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            // Revoke the token as well: leaving it valid would let the client
            // keep retrying with a credential that can never succeed.
            $user->currentAccessToken()?->delete();

            return response()->json([
                'success' => false,
                'message' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.',
                'error_code' => 'account_inactive',
            ], 403);
        }

        return $next($request);
    }
}
