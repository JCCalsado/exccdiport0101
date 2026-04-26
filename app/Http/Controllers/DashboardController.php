<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Notification;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Redirect users to their role-specific dashboards
        switch ($user->role?->value) {
            case 'student':
                return redirect()->route('student.dashboard');
            
            case 'accounting':
                return redirect()->route('accounting.dashboard');
            
            case 'admin':
                return redirect()->route('admin.dashboard');
            
            default:
                // Fallback for any other roles
                $notifications = Notification::query()
                    ->where(function ($q) use ($user) {
                        $q->where('target_role', $user->role?->value)
                          ->orWhere('target_role', 'all');
                    })
                    ->orderByDesc('start_date')
                    ->take(5)
                    ->get();

                return Inertia::render('Dashboard', [
                    'notifications' => $notifications,
                    'auth' => [
                        'user' => $user,
                    ],
                ]);
        }
    }
}