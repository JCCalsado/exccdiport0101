<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Models\WorkflowApproval;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        // ── Admin counts ──────────────────────────────────────────────────────
        $totalAdmins  = User::admins()->count();
        $activeAdmins = User::admins()->where('is_active', true)->count();

        // ── General user stats ─────────────────────────────────────────────────
        $totalUsers    = User::count();
        $totalStudents = User::students()->count();

        // ── Pending approvals ──────────────────────────────────────────────────
        $pendingApprovals = WorkflowApproval::where('status', 'pending')->count();

        // ── Recent notifications ───────────────────────────────────────────────
        // Keys are now snake_case to match every other prop in the system.
        $recentNotifications = Notification::orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Notification $n) => [
                'id'          => $n->id,
                'title'       => $n->title,
                'target_role' => $n->target_role,
                'start_date'  => $n->start_date,
                'end_date'    => $n->end_date,
                'created_at'  => $n->created_at,
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_admins'         => $totalAdmins,
                'active_admins'        => $activeAdmins,
                'inactive_admins'      => $totalAdmins - $activeAdmins,
                'total_users'          => $totalUsers,
                'total_students'       => $totalStudents,
                'pending_approvals'    => $pendingApprovals,
                'recent_notifications' => $recentNotifications,
                'system_health'        => [
                    'status'                => 'operational',
                    'database_status'       => 'operational',
                    'api_status'            => 'operational',
                    'authentication_status' => 'operational',
                ],
            ],
        ]);
    }
}