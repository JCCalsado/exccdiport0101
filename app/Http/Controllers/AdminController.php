<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminController extends Controller
{
    public function __construct(protected AdminService $adminService)
    {
        $this->middleware('auth:web');
        $this->middleware('role:admin');
    }

    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        // Include both admin and accounting users
        $admins = User::whereIn('department', ['Administrator', 'Accounting'])
            ->with(['createdByUser', 'updatedByUser'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Admin/Users/Index', [
            'admins'    => $admins,
            'stats'     => $this->adminService->getAdminStats(),
            'canManage' => auth()->user()->isAdmin() && auth()->user()->is_active,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $request->validate(['accept_terms' => 'sometimes|accepted']);

        try {
            $admin = $this->adminService->createAdmin($request->all(), $request->user());
            return redirect("/admin/users/{$admin->id}")
                ->with('success', 'Staff member created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        // Allow viewing both admin and accounting users
        if (! in_array($user->department, ['Administrator', 'Accounting'])) {
            abort(404);
        }

        return Inertia::render('Admin/Users/Show', [
            'admin'     => $user->load(['createdByUser', 'updatedByUser']),
            'canManage' => auth()->user()->isAdmin() && auth()->user()->is_active,
        ]);
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        // Allow editing both admin and accounting users
        if (! in_array($user->department, ['Administrator', 'Accounting'])) {
            abort(404);
        }

        return Inertia::render('Admin/Users/Edit', [
            'admin' => $user,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        // Allow updating both admin and accounting users
        if (! in_array($user->department, ['Administrator', 'Accounting'])) {
            abort(404);
        }

        try {
            $this->adminService->updateAdmin($user, $request->all(), $request->user());
            return redirect("/admin/users/{$user->id}")
                ->with('success', 'Staff member updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        abort(403, 'Hard deletion of admin accounts is not permitted.');
    }

    public function deactivate(Request $request, User $user)
    {
        $this->authorize('manageAdmins', $user);

        try {
            $this->adminService->deactivateAdmin($user, $request->user());
            return back()->with('success', 'Staff member deactivated successfully!');
        } catch (\InvalidArgumentException $e) {
            abort(403, $e->getMessage());
        }
    }

    public function reactivate(Request $request, User $user)
    {
        $this->authorize('manageAdmins', $user);

        // Allow reactivating both admin and accounting users
        if (! in_array($user->department, ['Administrator', 'Accounting'])) {
            abort(404);
        }

        try {
            $this->adminService->reactivateAdmin($user);
            return back()->with('success', 'Staff member reactivated successfully!');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}