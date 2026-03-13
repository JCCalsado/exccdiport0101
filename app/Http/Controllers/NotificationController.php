<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\StudentPaymentTerm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class NotificationController extends Controller
{
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

    public function create()
    {
        $this->authorize('create', Notification::class);

        $students = User::whereRole('student')
            ->select('id', 'first_name', 'last_name', 'middle_initial', 'email')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $paymentTerms = StudentPaymentTerm::distinct()
            ->orderBy('term_order')
            ->get(['id', 'term_name', 'term_order']);

        return Inertia::render('Admin/Notifications/Create', [
            'students'     => $students,
            'paymentTerms' => $paymentTerms,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Notification::class);

        $validated = $request->validate([
            'title'                   => 'required|string|max:255',
            'message'                 => 'nullable|string|max:2000',
            'type'                    => 'nullable|string|in:general,payment_due,payment_approved,payment_rejected',
            'start_date'              => 'required|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'due_date'                => 'nullable|date',
            'payment_term_id'         => 'nullable|integer|exists:student_payment_terms,id',
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

        DB::transaction(function () use ($validated) {
            $notification = Notification::create($validated);

            // Push the due_date into matching student_payment_terms rows so that
            // the "Next Payment Due" card on Dashboard and the Fees table in
            // AccountOverview both reflect the date the admin just set.
            $this->syncDueDateToPaymentTerms($validated);
        });

        return redirect('/admin/notifications')
            ->with('success', 'Notification created. Payment term due dates have been updated.');
    }

    public function show(Notification $notification)
    {
        $this->authorize('view', $notification);

        return Inertia::render('Admin/Notifications/Show', [
            'notification' => $notification,
        ]);
    }

    public function edit(Notification $notification)
    {
        $this->authorize('update', $notification);

        $students = User::whereRole('student')
            ->select('id', 'first_name', 'last_name', 'middle_initial', 'email')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $paymentTerms = StudentPaymentTerm::distinct()
            ->orderBy('term_order')
            ->get(['id', 'term_name', 'term_order']);

        return Inertia::render('Admin/Notifications/Edit', [
            'notification' => $notification,
            'students'     => $students,
            'paymentTerms' => $paymentTerms,
        ]);
    }

    public function update(Request $request, Notification $notification)
    {
        $this->authorize('update', $notification);

        $validated = $request->validate([
            'title'                   => 'required|string|max:255',
            'message'                 => 'nullable|string|max:2000',
            'type'                    => 'nullable|string|in:general,payment_due,payment_approved,payment_rejected',
            'start_date'              => 'required|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'due_date'                => 'nullable|date',
            'payment_term_id'         => 'nullable|integer|exists:student_payment_terms,id',
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

        DB::transaction(function () use ($notification, $validated) {
            $notification->update($validated);

            // Re-sync due_date to payment terms whenever the notification is updated.
            // This handles the case where admin changes the due date on an existing
            // notification — the term rows must reflect the new date immediately.
            $this->syncDueDateToPaymentTerms($validated);
        });

        return redirect('/admin/notifications')
            ->with('success', 'Notification updated. Payment term due dates have been updated.');
    }

    public function destroy(Notification $notification)
    {
        $this->authorize('delete', $notification);

        $notification->delete();

        return redirect('/admin/notifications')
            ->with('success', 'Notification deleted successfully.');
    }

    /**
     * Dismiss a notification for the current student.
     */
    public function dismiss(Request $request, Notification $notification)
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            $canDismiss = false;

            if ($notification->user_id !== null && $notification->user_id === $user->id) {
                $canDismiss = true;
            }

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

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Push the notification's due_date into the matching student_payment_terms rows.
     *
     * This is the single authoritative bridge between the Notifications admin page
     * and the live data that powers:
     *   - Dashboard "Next Payment Due" card  (reads student_payment_terms.due_date)
     *   - AccountOverview Fees table         (reads student_payment_terms.due_date)
     *
     * Resolution priority (most specific wins):
     *   1. term_ids set           → update exactly those term rows by PK
     *   2. payment_term_id set    → update that single term row
     *   3. target_term_name set   → update all terms with that name
     *      + if user_id is set    → restrict to that specific student only
     *
     * Only runs when:
     *   - type = 'payment_due'   (other types have no payment deadline)
     *   - due_date is present    (nothing to sync if no date was given)
     *
     * @param array $data  The validated request payload from store() / update().
     */
    private function syncDueDateToPaymentTerms(array $data): void
    {
        // Only payment_due notifications carry a payment deadline
        if (($data['type'] ?? '') !== 'payment_due') {
            return;
        }

        $dueDate = $data['due_date'] ?? null;
        if (! $dueDate) {
            return;
        }

        try {
            // ── Priority 1: explicit term IDs ────────────────────────────────
            if (! empty($data['term_ids'])) {
                $updated = StudentPaymentTerm::whereIn('id', $data['term_ids'])
                    ->update(['due_date' => $dueDate]);

                Log::info('NotificationController: synced due_date to term_ids', [
                    'term_ids' => $data['term_ids'],
                    'due_date' => $dueDate,
                    'updated'  => $updated,
                ]);

                return;
            }

            // ── Priority 2: single payment_term_id ──────────────────────────
            if (! empty($data['payment_term_id'])) {
                StudentPaymentTerm::where('id', $data['payment_term_id'])
                    ->update(['due_date' => $dueDate]);

                Log::info('NotificationController: synced due_date to payment_term_id', [
                    'payment_term_id' => $data['payment_term_id'],
                    'due_date'        => $dueDate,
                ]);

                return;
            }

            // ── Priority 3: target_term_name (broadcast to matching terms) ──
            if (! empty($data['target_term_name'])) {
                $query = StudentPaymentTerm::where('term_name', $data['target_term_name']);

                // If scoped to a specific student, restrict to their terms only
                if (! empty($data['user_id'])) {
                    $query->where('user_id', $data['user_id']);
                }

                $updated = $query->update(['due_date' => $dueDate]);

                Log::info('NotificationController: synced due_date by term_name', [
                    'target_term_name' => $data['target_term_name'],
                    'user_id'          => $data['user_id'] ?? 'all',
                    'due_date'         => $dueDate,
                    'updated'          => $updated,
                ]);

                return;
            }

            // ── No term filter: nothing to sync ─────────────────────────────
            // A payment_due notification with no term filter is a general banner.
            // We cannot safely update ALL payment terms with an arbitrary date,
            // so we skip the sync. The notification banner still appears on the
            // student dashboard with the due_date chip from admin_notifications.
            Log::info('NotificationController: no term filter — skipping due_date sync', [
                'due_date' => $dueDate,
            ]);

        } catch (\Throwable $e) {
            // Log but do not re-throw — the notification was saved successfully.
            // A sync failure should not roll back the notification creation.
            Log::error('NotificationController: failed to sync due_date to payment terms', [
                'error'   => $e->getMessage(),
                'data'    => $data,
            ]);
        }
    }
}