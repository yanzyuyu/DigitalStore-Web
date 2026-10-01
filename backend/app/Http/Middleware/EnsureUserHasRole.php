<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role gate, e.g. ->middleware('role:admin,seller').
 *
 * This is coarse-grained route protection. It answers "may this kind of user
 * reach this endpoint at all"; whether they may act on a *specific* record is
 * decided by a policy in the controller.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Autentikasi diperlukan.',
                'error_code' => 'unauthenticated',
            ], 401);
        }

        $allowed = array_map(
            static fn (string $role): ?UserRole => UserRole::tryFrom($role),
            $roles,
        );

        if (! in_array($user->role, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki hak akses untuk aksi ini.',
                'error_code' => 'forbidden_role',
            ], 403);
        }

        return $next($request);
    }
}
