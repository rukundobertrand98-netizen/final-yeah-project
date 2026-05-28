<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Unauthorized.');
        }

        $allowed = array_map(fn (string $r) => UserRole::from($r), $roles);

        if (! $user->isRole(...$allowed)) {
            abort(403, 'You do not have permission to access this area.');
        }

        if ($user->role === UserRole::Operator && ! $user->isOperatorApproved()) {
            abort(403, 'Your operator account is pending admin approval.');
        }

        return $next($request);
    }
}
