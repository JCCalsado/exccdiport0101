<?php

namespace App\Http\Middleware;

use App\Enums\UserRoleEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Map each role to its named dashboard route.
     * Used to redirect users who land on a page they don't have access to.
     */
    private const ROLE_DASHBOARDS = [
        'admin'      => 'admin.dashboard',
        'accounting' => 'accounting.dashboard',
        'student'    => 'student.dashboard',
    ];

    /**
     * Handle an incoming request.
     *
     * Checks two things in order:
     *   1. Is the user's account active? If not, log them out immediately.
     *   2. Does the user hold one of the required roles? If not, redirect to their dashboard.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Allowed roles for this route (e.g. 'admin', 'accounting')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        // Not authenticated — let the 'auth' middleware handle the redirect.
        if (! $user) {
            return redirect()->route('login');
        }

        // ── DEACTIVATION GATE ──────────────────────────────────────────────────
        // If the account has been deactivated since this session was created,
        // destroy the session immediately and send them back to login.
        // This closes the window where a deactivated user stays browsable
        // until their session naturally expires.
        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('flash.error', 'Your account has been deactivated. Please contact an administrator.');
        }
        // ──────────────────────────────────────────────────────────────────────

        $userRole = $user->role instanceof UserRoleEnum
            ? $user->role->value
            : (string) $user->role;

        // Role is authorized — continue to the controller.
        if (in_array($userRole, $roles, true)) {
            return $next($request);
        }

        // Role mismatch — redirect to the user's own dashboard with a flash warning.
        $dashboardRoute = self::ROLE_DASHBOARDS[$userRole] ?? 'dashboard';

        return redirect()
            ->route($dashboardRoute)
            ->with('flash.warning', 'You do not have permission to access that page.');
    }
}