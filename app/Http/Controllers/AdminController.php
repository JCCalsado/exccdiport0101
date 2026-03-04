<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminService;
use App\Enums\UserRoleEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AdminController extends Controller
{
    protected $adminService;

    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
        $this->middleware('auth:web');
        $this->middleware('role:admin');
    }

    /**
     * Display a listing of admin users.
     */
    public function index()
    {
        $this->authorize('viewAny', User::class);

        $admins = User::admins()
            ->with(['createdByUser', 'updatedByUser'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Admin/Users/Index', [
            'admins' => $admins,
            'stats' => $this->adminService->getAdminStats(),
        ]);
    }

    /**
     * Show the form for creating a new admin.
     */
    public function create()
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Create', [
            'adminTypes' => [
                ['value' => 'super', 'label' => 'Super Admin'],
                ['value' => 'manager', 'label' => 'Manager'],
                ['value' => 'operator', 'label' => 'Operator'],
            ],
        ]);
    }

    /**
     * Store a newly created admin in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        // Validate terms acceptance at the HTTP layer (only when provided)
        $request->validate([
            'accept_terms' => 'sometimes|accepted',
        ]);

        try {
            $admin = $this->adminService->createAdmin(
                $request->all(),
                $request->user()
            );

            return redirect("/admin/users/{$admin->id}")
                ->with('success', 'Admin user created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Display the specified admin user.
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        if (!$user->isAdmin()) {
            abort(404);
        }

        return Inertia::render('Admin/Users/Show', [
            'admin' => $user->load(['createdByUser', 'updatedByUser']),
        ]);
    }

    /**
     * Show the form for editing the specified admin.
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        if (!$user->isAdmin()) {
            abort(404);
        }

        return Inertia::render('Admin/Users/Edit', [
            'admin' => $user,
            'adminTypes' => [
                ['value' => 'super', 'label' => 'Super Admin'],
                ['value' => 'manager', 'label' => 'Manager'],
                ['value' => 'operator', 'label' => 'Operator'],
            ],
        ]);
    }

    /**
     * Update the specified admin in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        if (!$user->isAdmin()) {
            abort(404);
        }

        try {
            $this->adminService->updateAdmin(
                $user,
                $request->all(),
                $request->user()
            );

            return redirect("/admin/users/{$user->id}")
                ->with('success', 'Admin updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Hard delete is not permitted — use deactivate instead.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        // Policy always returns false for delete; this line is never reached
        abort(403, 'Hard deletion of admin accounts is not permitted.');
    }

    /**
     * Deactivate the specified admin.
     */
    public function deactivate(Request $request, User $user)
    {
        $this->authorize('manageAdmins', $user);

        try {
            $this->adminService->deactivateAdmin($user);

            return back()->with('success', 'Admin deactivated successfully!');
        } catch (\InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }
    }

    /**
     * Reactivate the specified admin.
     */
    public function reactivate(Request $request, User $user)
    {
        $this->authorize('manageAdmins', $user);

        if (!$user->isAdmin()) {
            abort(404);
        }

        try {
            $this->adminService->reactivateAdmin($user);
            return back()->with('success', 'Admin reactivated successfully!');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}