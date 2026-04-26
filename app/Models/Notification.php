<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Custom Admin Notification Model
 *
 * Stored in `admin_notifications` table — separate from Laravel's built-in
 * `notifications` table. See docs/NOTIFICATION_ARCHITECTURE.md.
 */
class Notification extends Model
{
    use HasFactory;

    protected $table = 'admin_notifications';

    protected $fillable = [
        'title', 'message', 'type', 'start_date', 'end_date', 'due_date',
        'payment_term_id', 'target_role', 'user_id', 'user_ids', 'is_active',
        'is_complete', 'dismissed_at', 'read_at', 'term_ids', 'target_term_name',
        'trigger_days_before_due',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'end_date'     => 'date',
        'due_date'     => 'date',
        'is_active'    => 'boolean',
        'is_complete'  => 'boolean',
        'dismissed_at' => 'datetime',
        'read_at'      => 'datetime',
        'term_ids'     => 'array',
        'user_ids'     => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(StudentPaymentTerm::class, 'payment_term_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive($query)
    {
        return $query
            ->where('is_active', true)
            ->where('is_complete', false)
            ->whereNull('dismissed_at');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope: notifications visible to a specific user.
     *
     * Matches on:
     *   1. Direct user_id assignment
     *   2. JSON user_ids array containing this user
     *   3. Broadcast / role-based (no specific user target, matching role + term)
     *
     * FIX: All nested closures now explicitly capture $table, $driver, and $user
     * via `use(...)`. PHP closures do NOT inherit parent closure scope automatically —
     * every level of nesting must re-declare the variables it needs.
     */
    public function scopeForUser($query, int|string $userIdentifier)
    {
        if (is_string($userIdentifier) && str_contains($userIdentifier, '@')) {
            $user = User::where('email', $userIdentifier)->first();
        } else {
            $user = User::find($userIdentifier);
        }

        if (! $user) {
            return $query->whereRaw('0 = 1');
        }

        $driver = DB::getDriverName();
        $table  = $this->getTable();

        return $query->where(function ($q) use ($user, $driver, $table) {

            // 1. Single specific user_id match
            $q->where('user_id', $user->id)

              // 2. Multi-student user_ids JSON array contains this user
              ->orWhere(function ($qm) use ($user, $driver, $table) {
                  $qm->whereNotNull('user_ids')
                     ->where(function ($qi) use ($user, $driver, $table) {
                         if ($driver === 'sqlite') {
                             $qi->whereRaw(
                                 "EXISTS (SELECT 1 FROM json_each({$table}.user_ids) WHERE json_each.value = ?)",
                                 [$user->id]
                             );
                         } else {
                             $qi->whereRaw(
                                 "JSON_CONTAINS({$table}.user_ids, JSON_ARRAY(?))",
                                 [$user->id]
                             );
                         }
                     });
              })

              // 3. Broadcast / role-based notifications (no specific user targeting)
              // FIX: $driver and $table are now explicitly passed into $q2 and all
              // nested closures that reference them.
              ->orWhere(function ($q2) use ($user, $driver, $table) {
                  $roleString = $user->role instanceof \BackedEnum
                      ? $user->role->value
                      : (string) $user->role;

                  $q2->whereNull('user_id')
                     ->whereNull('user_ids')
                     ->where(function ($q3) use ($user, $roleString) {
                         $q3->where('target_role', $roleString)
                            ->orWhere('target_role', 'all');
                     })
                     ->where(function ($q4) use ($user) {
                         $q4->where(function ($inner) {
                                $inner->whereNull('target_term_name')
                                      ->orWhere('target_term_name', '');
                            })
                            ->orWhereExists(function ($sub) use ($user) {
                                $sub->from('student_payment_terms')
                                    ->join('student_assessments', 'student_assessments.id', '=', 'student_payment_terms.student_assessment_id')
                                    ->where('student_assessments.user_id', $user->id)
                                    ->whereColumn('student_payment_terms.term_name', 'admin_notifications.target_term_name');
                            });
                     })
                     // FIX: $table and $driver are now properly captured here
                     ->where(function ($q5) use ($user, $table, $driver) {
                         $q5->whereNull('term_ids')
                            ->orWhereRaw("JSON_LENGTH({$table}.term_ids) = 0")
                            ->orWhereExists(function ($sub) use ($user, $table, $driver) {
                                $sub->from('student_payment_terms')
                                    ->join('student_assessments', 'student_assessments.id', '=', 'student_payment_terms.student_assessment_id')
                                    ->where('student_assessments.user_id', $user->id)
                                    ->whereRaw(
                                        $driver === 'sqlite'
                                            ? "EXISTS (SELECT 1 FROM json_each({$table}.term_ids) WHERE json_each.value = student_payment_terms.id)"
                                            : "JSON_CONTAINS({$table}.term_ids, JSON_ARRAY(student_payment_terms.id))"
                                    );
                            });
                     });
              });
        });
    }

    public function scopeWithinDateRange($query)
    {
        $today = now()->toDateString();

        return $query
            ->where(function ($q) use ($today) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today);
            });
    }

    public function scopeForDueDateTrigger($query, User $user)
    {
        $today        = now()->toDateString();
        $maxLookahead = now()->addDays(90)->toDateString();
        $table        = $this->getTable();

        return $query->where(function ($q) use ($user, $today, $maxLookahead, $table) {
            $q->whereNull('trigger_days_before_due')
              ->orWhere(function ($q2) use ($user, $today, $maxLookahead, $table) {
                  $q2->whereNotNull('trigger_days_before_due')
                     ->whereExists(function ($sub) use ($user, $today, $maxLookahead, $table) {
                         $sub->from('student_payment_terms')
                             ->join('student_assessments', 'student_assessments.id', '=', 'student_payment_terms.student_assessment_id')
                             ->where('student_assessments.user_id', $user->id)
                             ->where('student_payment_terms.balance', '>', 0)
                             ->whereNotNull('student_payment_terms.due_date')
                             ->where('student_payment_terms.due_date', '>=', $today)
                             ->where('student_payment_terms.due_date', '<=', $maxLookahead)
                             ->whereRaw(
                                 'student_payment_terms.due_date <= ' .
                                 self::addDaysExpression("{$table}.trigger_days_before_due")
                             );
                     });
              });
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isCurrentlyActive(): bool
    {
        $today = now()->toDateString();

        return $this->is_active
            && ! $this->is_complete
            && ! $this->dismissed_at
            && (! $this->start_date || $this->start_date->toDateString() <= $today)
            && (! $this->end_date   || $this->end_date->toDateString()   >= $today);
    }

    public function markComplete(): void { $this->update(['is_complete' => true]); }
    public function markDismissed(): void { $this->update(['dismissed_at' => now()]); }

    /**
     * Returns a SQL expression that adds an integer column value (days) to today's date.
     * MySQL uses DATE_ADD; SQLite uses date().
     */
    protected static function addDaysExpression(string $columnExpression): string
    {
        $driver = DB::getDriverName();

        return $driver === 'sqlite'
            ? "date('now', '+' || {$columnExpression} || ' days')"
            : "DATE_ADD(CURDATE(), INTERVAL {$columnExpression} DAY)";
    }
}