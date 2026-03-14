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
        // One query for the total, one grouped query for the type breakdown —
        // replaces the previous 5 separate where('role','admin') queries.
        $totalAdmins  = User::admins()->count();
        $activeAdmins = User::admins()->where('is_active', true)->count();

        $adminsByType = User::admins()
            ->select('admin_type', DB::raw('COUNT(*) as count'))
            ->groupBy('admin_type')
            ->pluck('count', 'admin_type');

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
                'super_admins'         => (int) ($adminsByType[User::ADMIN_TYPE_SUPER]    ?? 0),
                'managers'             => (int) ($adminsByType[User::ADMIN_TYPE_MANAGER]  ?? 0),
                'operators'            => (int) ($adminsByType[User::ADMIN_TYPE_OPERATOR] ?? 0),
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