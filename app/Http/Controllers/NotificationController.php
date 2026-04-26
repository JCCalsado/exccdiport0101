<?php

namespace App\Http\Controllers;

use App\Mail\AccountNotification;
use App\Models\Notification;
use App\Models\StudentPaymentTerm;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $notifications = Notification::orderByDesc('created_at')
                ->get()
                ->map(fn ($n) => [
                    'id'                      => $n->id,
                    'title'                   => $n->title,
                    'message'                 => $n->message,
                    'type'                    => $n->type,
                    'target_role'             => $n->target_role,
                    'start_date'              => $n->start_date?->toDateString(),
                    'end_date'                => $n->end_date?->toDateString(),
                    'due_date'                => $n->due_date?->toDateString(),
                    'payment_term_id'         => $n->payment_term_id,
                    'is_active'               => $n->is_active,
                    'is_complete'             => $n->is_complete,
                    'target_term_name'        => $n->target_term_name,
                    'term_ids'                => $n->term_ids,
                    'trigger_days_before_due' => $n->trigger_days_before_due,
                    'user_id'                 => $n->user_id,
                    'user_ids'                => $n->user_ids,
                    'dismissed_at'            => $n->dismissed_at?->toDateTimeString(),
                    'created_at'              => $n->created_at->toDateString(),
                    'updated_at'              => $n->updated_at->toDateString(),
                ]);

            return Inertia::render('Admin/Notifications/Index', [
                'notifications' => $notifications,
                'role'          => $user->role,
            ]);
        }

        return $this->studentIndex($request);
    }

    /**
     * Student-facing notifications page.
     *
     * Returns TWO collections:
     *   - active:   currently visible, not dismissed
     *   - history:  dismissed OR expired (end_date passed) — stays visible so
     *               students have a record of past comms
     */
    public function studentIndex(Request $request): \Inertia\Response
    {
        $user = $request->user();

        // Active notifications (not dismissed, within date range, due-date trigger met).
        $active = Notification::where('is_active', true)
            ->where('is_complete', false)
            ->whereNull('dismissed_at')
            ->forUser($user->id)
            ->withinDateRange()
            ->forDueDateTrigger($user)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($n) => $this->mapNotification($n));

        // History: dismissed notifications OR expired ones scoped to this user.
        // No date-range or dismissed filter here — we want the full record.
        $history = Notification::where('is_active', true)
            ->forUser($user->id)
            ->where(function ($q) {
                $q->whereNotNull('dismissed_at')
                  ->orWhere('is_complete', true)
                  ->orWhere(function ($q2) {
                      $today = now()->toDateString();
                      $q2->whereNotNull('end_date')->where('end_date', '<', $today);
                  });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($n) => $this->mapNotification($n));

        // Mark active notifications as read when student opens the page.
        Notification::where('is_active', true)
            ->where('is_complete', false)
            ->whereNull('dismissed_at')
            ->forUser($user->id)
            ->withinDateRange()
            ->forDueDateTrigger($user)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        Cache::forget("unread_notifications_count:{$user->id}");

        return Inertia::render('Notifications/Index', [
            'active'  => $active,
            'history' => $history,
        ]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();

        Notification::where('is_active', true)
            ->where('is_complete', false)
            ->whereNull('dismissed_at')
            ->forUser($user->id)
            ->withinDateRange()
            ->forDueDateTrigger($user)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        Cache::forget("unread_notifications_count:{$user->id}");

        return back();
    }

    public function create()
    {
        $this->authorize('create', Notification::class);

        $students = User::whereRole('student')
            ->select('id', 'first_name', 'last_name', 'middle_initial', 'email')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($s) => [
                'id'    => $s->id,
                'name'  => "{$s->last_name}, {$s->first_name}" . ($s->middle_initial ? " {$s->middle_initial}." : ''),
                'email' => $s->email,
            ]);

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

        $validated = $this->validateNotification($request);
        $validated = $this->normalizeNotificationData($validated);

        // Multi-student selection takes priority; clear single user_id.
        if (! empty($validated['user_ids'])) {
            $validated['user_id']     = null;
            $validated['target_role'] = 'student';
        } elseif (! empty($validated['user_id'])) {
            $validated['user_ids']    = null;
            $validated['target_role'] = 'student';
        }

        DB::transaction(function () use ($validated) {
            Notification::create($validated);
        });

        $this->syncDueDateToPaymentTerms($validated);
        $this->dispatchNotificationEmails($validated);

        return redirect('/admin/notifications')
            ->with('success', 'Notification created and emails queued for delivery.');
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
            ->get()
            ->map(fn ($s) => [
                'id'    => $s->id,
                'name'  => "{$s->last_name}, {$s->first_name}" . ($s->middle_initial ? " {$s->middle_initial}." : ''),
                'email' => $s->email,
            ]);

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

        $validated = $this->validateNotification($request);
        $validated = $this->normalizeNotificationData($validated);

        if (! empty($validated['user_ids'])) {
            $validated['user_id']     = null;
            $validated['target_role'] = 'student';
        } elseif (! empty($validated['user_id'])) {
            $validated['user_ids']    = null;
            $validated['target_role'] = 'student';
        }

        DB::transaction(function () use ($notification, $validated) {
            $notification->update($validated);
        });

        $this->syncDueDateToPaymentTerms($validated);
        $this->dispatchNotificationEmails($validated);

        return redirect('/admin/notifications')
            ->with('success', 'Notification updated and emails re-queued for delivery.');
    }

    public function destroy(Notification $notification)
    {
        $this->authorize('delete', $notification);
        $notification->delete();

        return redirect('/admin/notifications')
            ->with('success', 'Notification deleted successfully.');
    }

    public function dismiss(Request $request, Notification $notification)
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            if ($notification->user_id !== null && $notification->user_id !== $user->id) {
                abort(403, 'You are not authorised to dismiss this notification.');
            }

            // Multi-student: check if this user is in user_ids
            if ($notification->user_ids !== null && ! in_array($user->id, array_map('intval', $notification->user_ids), true)) {
                abort(403, 'You are not authorised to dismiss this notification.');
            }

            if ($notification->user_id === null && $notification->user_ids === null) {
                $roleString = $user->role instanceof \BackedEnum
                    ? $user->role->value
                    : (string) $user->role;

                if (! in_array($notification->target_role, [$roleString, 'all'], true)) {
                    abort(403, 'You are not authorised to dismiss this notification.');
                }
            }
        }

        $notification->markDismissed();
        Cache::forget("unread_notifications_count:{$user->id}");

        return back()->with('success', 'Notification dismissed.');
    }

    // -------------------------------------------------------------------------
    // Email Dispatch
    // -------------------------------------------------------------------------

    private function dispatchNotificationEmails(array $data): void
    {
        try {
            $recipients = $this->resolveEmailRecipients($data);

            if ($recipients->isEmpty()) {
                Log::info('NotificationController: no email recipients resolved', [
                    'target_role' => $data['target_role'] ?? null,
                    'user_id'     => $data['user_id'] ?? null,
                    'user_ids'    => $data['user_ids'] ?? null,
                ]);
                return;
            }

            $actionUrl   = null;
            $actionLabel = null;

            if (($data['type'] ?? '') === 'payment_due') {
                $actionUrl   = route('student.account', ['tab' => 'payment']);
                $actionLabel = 'View Payment Details';
            }

            $emailType = match ($data['type'] ?? 'general') {
                'payment_due'      => 'warning',
                'payment_approved' => 'success',
                'payment_rejected' => 'error',
                default            => 'info',
            };

            $queued = 0;

            foreach ($recipients as $user) {
                if (empty($user->email)) {
                    continue;
                }

                Mail::to($user->email)->queue(
                    new AccountNotification(
                        studentName:         "{$user->first_name} {$user->last_name}",
                        notificationTitle:   $data['title'],
                        notificationMessage: $data['message'] ?? '',
                        notificationType:    $emailType,
                        actionUrl:           $actionUrl,
                        actionLabel:         $actionLabel,
                    )
                );

                $queued++;
            }

            Log::info('NotificationController: queued notification emails', [
                'queued' => $queued,
                'type'   => $data['type'] ?? 'general',
                'title'  => $data['title'],
            ]);

        } catch (\Throwable $e) {
            Log::error('NotificationController: failed to dispatch notification emails', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function resolveEmailRecipients(array $data): \Illuminate\Database\Eloquent\Collection
    {
        // Multi-student user_ids array — highest priority.
        if (! empty($data['user_ids']) && is_array($data['user_ids'])) {
            return User::whereIn('id', $data['user_ids'])
                ->whereNotNull('email')
                ->get();
        }

        // Single user_id.
        if (! empty($data['user_id'])) {
            return User::where('id', $data['user_id'])
                ->whereNotNull('email')
                ->get();
        }

        $role = $data['target_role'] ?? null;

        if ($role === 'all') {
            return User::whereNotNull('email')
                ->where('is_active', true)
                ->get();
        }

        if (in_array($role, ['student', 'accounting', 'admin'], true)) {
            return User::where('role', $role)
                ->whereNotNull('email')
                ->where('is_active', true)
                ->get();
        }

        return collect();
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    private function mapNotification(Notification $n): array
    {
        return [
            'id'              => $n->id,
            'title'           => $n->title,
            'message'         => $n->message,
            'type'            => $n->type,
            'start_date'      => $n->start_date?->toDateString(),
            'end_date'        => $n->end_date?->toDateString(),
            'due_date'        => $n->due_date?->toDateString(),
            'payment_term_id' => $n->payment_term_id,
            'target_role'     => $n->target_role,
            'is_active'       => $n->is_active,
            'is_complete'     => $n->is_complete,
            'dismissed_at'    => $n->dismissed_at?->toDateTimeString(),
            'created_at'      => $n->created_at->toDateTimeString(),
        ];
    }

    private function normalizeNotificationData(array $data): array
    {
        if (isset($data['target_term_name']) && $data['target_term_name'] === '') {
            $data['target_term_name'] = null;
        }

        if (isset($data['term_ids']) && is_array($data['term_ids']) && count($data['term_ids']) === 0) {
            $data['term_ids'] = null;
        }

        if (isset($data['user_ids']) && is_array($data['user_ids']) && count($data['user_ids']) === 0) {
            $data['user_ids'] = null;
        }

        return $data;
    }

    private function validateNotification(Request $request): array
    {
        return $request->validate([
            'title'                   => 'required|string|max:255',
            'message'                 => 'nullable|string|max:2000',
            'type'                    => 'nullable|string|in:general,payment_due,payment_approved,payment_rejected',
            'start_date'              => 'required|date',
            'end_date'                => 'nullable|date|after_or_equal:start_date',
            'due_date'                => 'nullable|date',
            'payment_term_id'         => 'nullable|integer|exists:student_payment_terms,id',
            'target_role'             => 'required|string|in:student,accounting,admin,all',
            'user_id'                 => 'nullable|integer|exists:users,id',
            'user_ids'                => 'nullable|array',
            'user_ids.*'              => 'integer|exists:users,id',
            'is_active'               => 'boolean',
            'term_ids'                => 'nullable|array',
            'term_ids.*'              => 'integer|exists:student_payment_terms,id',
            'target_term_name'        => 'nullable|string|in:Upon Registration,Prelim,Midterm,Semi-Final,Final',
            'trigger_days_before_due' => 'nullable|integer|min:0|max:90',
        ]);
    }

    private function syncDueDateToPaymentTerms(array $data): void
    {
        if (($data['type'] ?? '') !== 'payment_due') {
            return;
        }

        $dueDate = $data['due_date'] ?? null;
        if (! $dueDate) {
            return;
        }

        try {
            if (! empty($data['term_ids'])) {
                StudentPaymentTerm::whereIn('id', $data['term_ids'])
                    ->update(['due_date' => $dueDate]);
                return;
            }

            if (! empty($data['payment_term_id'])) {
                StudentPaymentTerm::where('id', $data['payment_term_id'])
                    ->update(['due_date' => $dueDate]);
                return;
            }

            if (! empty($data['target_term_name'])) {
                $query = StudentPaymentTerm::where('term_name', $data['target_term_name']);

                if (! empty($data['user_id'])) {
                    $query->where('user_id', $data['user_id']);
                } elseif (! empty($data['user_ids'])) {
                    $query->whereIn('user_id', $data['user_ids']);
                }

                $query->update(['due_date' => $dueDate]);
            }

        } catch (\Throwable $e) {
            Log::error('NotificationController: failed to sync due_date to payment terms', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
