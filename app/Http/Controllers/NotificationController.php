<?php

namespace App\Http\Controllers;

use App\Enums\UserRoleEnum;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    /**
     * Display all notifications (admin sees all; others see their own active ones).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $notifications = Notification::orderByDesc('created_at')->get();
        } else {
            $notifications = Notification::active()
                ->forUser($user->id)
                ->withinDateRange()
                ->orderByDesc('start_date')
                ->get();
        }

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'role'          => $user->role,
        ]);
    }

    /**
     * Show create notification form.
     */
    public function create()
    {
        $this->authorize('create', Notification::class);

        $students = User::whereRole('student')
            ->select('id', 'first_name', 'last_name', 'middle_initial', 'email')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $paymentTerms = \App\Models\StudentPaymentTerm::distinct()
            ->orderBy('term_order')
            ->get(['id', 'term_name', 'term_order']);

        return Inertia::render('Admin/Notifications/Create', [
            'students'     => $students,
            'paymentTerms' => $paymentTerms,
        ]);
    }

    /**
     * Store a new notification.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Notification::class);

        $validated = $request->validate([
            'title'                   => 'required|string|max:255',
            'message'                 => 'nullable|string|max:2000',
            'type'                    => 'nullable|string|in:general,payment_due,payment_approved,payment_rejected',
            'start_date'              => 'required|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'target_role'             => 'required|string|in:student,accounting,admin,all',
            'user_id'                 => 'nullable|integer|exists:users,id',
            'is_active'               => 'boolean',
            'term_ids'                => 'nullable|array',
            'term_ids.*'              => 'integer|exists:student_payment_terms,id',
            'target_term_name'        => 'nullable|string|in:Upon Registration,Prelim,Midterm,Semi-Final,Final',
            'trigger_days_before_due' => 'nullable|integer|min:0|max:90',
        ]);

        if (! empty($validated['user_id'])) {
            $validated['target_role'] = 'student';
        }

        Notification::create($validated);

        return redirect('/admin/notifications')
            ->with('success', 'Notification created successfully.');
    }

    /**
     * Show a specific notification.
     */
    public function show(Notification $notification)
    {
        $this->authorize('view', $notification);

        return Inertia::render('Admin/Notifications/Show', [
            'notification' => $notification,
        ]);
    }

    /**
     * Show edit notification form.
     */
    public function edit(Notification $notification)
    {
        $this->authorize('update', $notification);

        $students = User::whereRole('student')
            ->select('id', 'first_name', 'last_name', 'middle_initial', 'email')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $paymentTerms = \App\Models\StudentPaymentTerm::distinct()
            ->orderBy('term_order')
            ->get(['id', 'term_name', 'term_order']);

        return Inertia::render('Admin/Notifications/Edit', [
            'notification' => $notification,
            'students'     => $students,
            'paymentTerms' => $paymentTerms,
        ]);
    }

    /**
     * Update a notification.
     */
    public function update(Request $request, Notification $notification)
    {
        $this->authorize('update', $notification);

        $validated = $request->validate([
            'title'                   => 'required|string|max:255',
            'message'                 => 'nullable|string|max:2000',
            'type'                    => 'nullable|string|in:general,payment_due,payment_approved,payment_rejected',
            'start_date'              => 'required|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'target_role'             => 'required|string|in:student,accounting,admin,all',
            'user_id'                 => 'nullable|integer|exists:users,id',
            'is_active'               => 'boolean',
            'term_ids'                => 'nullable|array',
            'term_ids.*'              => 'integer|exists:student_payment_terms,id',
            'target_term_name'        => 'nullable|string|in:Upon Registration,Prelim,Midterm,Semi-Final,Final',
            'trigger_days_before_due' => 'nullable|integer|min:0|max:90',
        ]);

        if (! empty($validated['user_id'])) {
            $validated['target_role'] = 'student';
        }

        $notification->update($validated);

        return redirect('/admin/notifications')
            ->with('success', 'Notification updated successfully.');
    }

    /**
     * Delete a notification.
     */
    public function destroy(Notification $notification)
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return redirect('/admin/notifications')
            ->with('success', 'Notification deleted successfully.');
    }

    /**
     * Mark a notification as dismissed by the currently authenticated user.
     *
     * Bug 1 fix — ownership check:
     *   The previous implementation called markDismissed() with NO authorization.
     *   Any authenticated user could POST /notifications/{any_id}/dismiss and
     *   silently wipe another student's banner. We now enforce ownership:
     *     (a) Notification directly addressed to this user  (user_id match), OR
     *     (b) Broadcast notification this user's role can receive (user_id = null).
     *   Admins may dismiss any notification for testing/management purposes.
     *
     * Bug 2 fix — Enum-safe role comparison:
     *   $user->role is a UserRoleEnum instance, NOT a plain string.
     *   The previous code used in_array($target_role, [$user->role, 'all']).
     *   Since UserRoleEnum::STUDENT !== 'student' (object vs string), the
     *   comparison always returned false and students could never dismiss
     *   broadcast notifications.  We now compare against ->value explicitly.
     */
    public function dismiss(Request $request, Notification $notification)
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            $canDismiss = false;

            // Case A: notification is addressed directly to this user
            if ($notification->user_id !== null && $notification->user_id === $user->id) {
                $canDismiss = true;
            }

            // Case B: broadcast notification for this user's role
            // Bug 2 fix: use ->value to get the plain string from the Enum
            if ($notification->user_id === null) {
                $roleString = $user->role instanceof \BackedEnum
                    ? $user->role->value
                    : (string) $user->role;

                $canDismiss = in_array($notification->target_role, [$roleString, 'all'], true);
            }

            if (! $canDismiss) {
                abort(403, 'You are not authorised to dismiss this notification.');
            }
        }

        $notification->markDismissed();

        return back()->with('success', 'Notification dismissed.');
    }
}