<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Enums\UserRoleEnum;
use App\Enums\AdminTypeEnum;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_GRADUATED = 'graduated';
    const STATUS_DROPPED = 'dropped';

    // Admin type constants
    const ADMIN_TYPE_SUPER = 'super';
    const ADMIN_TYPE_MANAGER = 'manager';
    const ADMIN_TYPE_OPERATOR = 'operator';

    protected $fillable = [
        'last_name',
        'first_name',
        'middle_initial',
        'email',
        'password',
        'birthday',
        'address',
        'phone',
        'student_id',
        'profile_picture',
        'course',
        'year_level',
        'faculty',
        'status',
        'role',
        'is_active',
        'terms_accepted_at',
        'permissions',
        'department',
        'admin_type',
        'created_by',
        'updated_by',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Set the appends property to include virtual attributes
    protected $appends = ['name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRoleEnum::class,
            'birthday' => 'date',
            'terms_accepted_at' => 'datetime',
            'permissions' => 'json',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // ========== RELATIONSHIPS ==========

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function account(): HasOne
    {
        return $this->hasOne(Account::class);
    }

    public function latestAssessment(): HasOne
    {
        return $this->hasOne(\App\Models\StudentAssessment::class, 'user_id')
                    ->where('status', 'active')
                    ->latestOfMany();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // Admin audit relationships
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ========== ACCESSORS ==========

    /**
     * Get the user's full name.
     * This is the main accessor that will be serialized in API responses.
     */
    public function getNameAttribute(): string
    {
        $mi = $this->middle_initial ? ' ' . strtoupper($this->middle_initial) . '.' : '';
        return "{$this->last_name}, {$this->first_name}{$mi}";
    }

    /**
     * Get the user's full name (alternative format).
     * Use this for display purposes where you want "Last, First MI."
     */
    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_initial ? "{$this->middle_initial}." : '';
        return "{$this->last_name}, {$this->first_name} {$mi}";
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', UserRoleEnum::ADMIN->value);
    }

    public function scopeStudents($query)
    {
        return $query->where('role', UserRoleEnum::STUDENT->value);
    }

    public function scopeAccounting($query)
    {
        return $query->where('role', UserRoleEnum::ACCOUNTING->value);
    }

    public function scopeTermsAccepted($query)
    {
        return $query->whereNotNull('terms_accepted_at');
    }

    // ========== ADMIN HELPERS ==========

    /**
     * Check if user is an admin
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRoleEnum::ADMIN;
    }

    /**
     * Check if user is a super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && $this->admin_type === self::ADMIN_TYPE_SUPER;
    }

    /**
     * Check if user has accepted terms & conditions
     */
    public function hasAcceptedTerms(): bool
    {
        return $this->terms_accepted_at !== null;
    }

    /**
     * Accept terms & conditions
     */
    public function acceptTerms(): void
    {
        $this->update([
            'terms_accepted_at' => now(),
        ]);
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $permission): bool
    {
        // Super admins have all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check if user is active
        if (!$this->is_active) {
            return false;
        }

        // Check role-based permissions (implement based on admin_type)
        if ($this->isAdmin()) {
            // Define permissions by admin type
            $permissionsByType = [
                self::ADMIN_TYPE_SUPER => ['*'], // all permissions
                self::ADMIN_TYPE_MANAGER => ['manage_fees', 'manage_workflows', 'approve_payments', 'view_users', 'manage_admins', 'view_audit_logs'],
                self::ADMIN_TYPE_OPERATOR => ['approve_payments', 'view_users', 'view_audit_logs'],
            ];

            $allowedPermissions = $permissionsByType[$this->admin_type] ?? [];
            return in_array('*', $allowedPermissions) || in_array($permission, $allowedPermissions);
        }

        return false;
    }

    /**
     * Check multiple permissions (OR logic)
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check multiple permissions (AND logic)
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Update user activity timestamp
     */
    public function recordLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    // ========== VALIDATION RULES ==========

    /**
     * Get validation rules for user updates
     */
    public static function getValidationRules($userId = null): array
    {
        return [
            'student_id' => 'nullable|string|unique:users,student_id,' . $userId,
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'course' => 'nullable|string|max:100',
            'year_level' => 'nullable|string|max:50',
            'faculty' => 'nullable|string|max:100',
            'status' => 'required|in:active,graduated,dropped',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    /**
     * Get validation rules for admin creation/update
     */
    public static function getAdminValidationRules($userId = null): array
    {
        $uniqueEmail = $userId ? "unique:users,email,{$userId}" : 'unique:users,email';

        return [
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'middle_initial' => 'nullable|string|max:1',
            'email' => "required|email|{$uniqueEmail}",
            'password' => $userId ? 'nullable|min:8|confirmed' : 'required|min:8|confirmed',
            'admin_type' => 'required|in:super,manager,operator',
            'department' => 'nullable|string|max:100',
            'is_active' => 'boolean',
        ];
    }
}