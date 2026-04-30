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

    /**
     * List all admin and accounting staff.
     * Admin has full management capability over Accounting users.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $admins = User::whereIn('department', ['Administrator', 'Accounting'])
            ->with(['createdByUser', 'updatedByUser'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Admin/Users/Index', [
            'admins'    => $admins,
            'stats'     => $this->adminService->getAdminStats(),
            // Admin CAN manage — specifically they can create/edit/toggle Accounting users
            'canManage' => true,
        ]);
    }

    /**
     * View a single staff profile.
     */
    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        if (! in_array($user->department, ['Administrator', 'Accounting'])) {
            abort(404);
        }

        return Inertia::render('Admin/Users/Show', [
            'admin'     => $user->load(['createdByUser', 'updatedByUser']),
            // canManage is true only for Accounting department — enforced in the view
            'canManage' => $user->department === 'Accounting',
        ]);
    }

    /**
     * Show the create form — creates Accounting staff only.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Create');
    }

    /**
     * Store a new Accounting staff user.
     * Department is forced to 'Accounting' in the service layer
     * to prevent Admin from creating other Admin accounts.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        // Force department to Accounting — Admin cannot create Admin accounts
        $data = array_merge($request->all(), ['department' => 'Accounting']);

        try {
            $admin = $this->adminService->createAdmin($data, $request->user());
            return redirect()->route('users.show', $admin->id)
                ->with('success', 'Accounting staff member created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Show the edit form for an Accounting department user.
     * Editing Administrator accounts is not permitted.
     */
    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        // Only Accounting users are editable — policy already enforces this,
        // but we double-guard here for clarity.
        if ($user->department !== 'Accounting') {
            abort(403, 'Administrator accounts cannot be edited.');
        }

        return Inertia::render('Admin/Users/Edit', [
            'admin' => $user,
        ]);
    }

    /**
     * Update an Accounting department user.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        if ($user->department !== 'Accounting') {
            abort(403, 'Administrator accounts cannot be edited.');
        }

        // Prevent department from being changed to Administrator via the form
        $data = array_merge($request->all(), ['department' => 'Accounting']);

        try {
            $this->adminService->updateAdmin($user, $data, $request->user());
            return redirect()->route('users.show', $user->id)
                ->with('success', 'Staff member updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
    }

    /**
     * Hard deletion is never allowed.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        abort(403, 'Hard deletion of accounts is not permitted. Use deactivate instead.');
    }

    /**
     * Deactivate an Accounting staff user.
     */
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

    /**
     * Reactivate an Accounting staff user.
     */
    public function reactivate(Request $request, User $user)
    {
        $this->authorize('manageAdmins', $user);

        try {
            $this->adminService->reactivateAdmin($user);
            return back()->with('success', 'Staff member reactivated successfully!');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}