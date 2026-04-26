# Admin User Implementation Plan
## Complete Design & Documentation

**Project:** EXCCDI Port 01  
**Date:** February 18, 2026  
**Status:** Design Phase

---

## EXECUTIVE SUMMARY

This document details the complete implementation plan for enhancing Admin user functionality in the EXCCDI system. The current system has a basic role-based access control (RBAC) system with admin role, but lacks:
- Terms & Conditions acceptance tracking
- Fine-grained permission system
- Audit trail (created_by, updated_by)
- Admin-specific UI/forms
- Comprehensive admin policy layer

**Recommended Approach:** Enhance the existing RBAC system by:
1. Adding specialized admin fields (terms_accepted_at, audit fields)
2. Implementing a permission system for fine-grained control
3. Creating comprehensive admin policies
4. Building admin user management UI
5. Establishing audit logging mechanisms

---

## PART 1: CURRENT SYSTEM ANALYSIS

### 1.1 How Users Are Currently Implemented

#### Models & Entities
- **Location:** `app/Models/User.php`
- **Key Fields:**
  - `id`, `email` (unique), `password` (hashed)
  - `name` (deprecated), `last_name`, `first_name`, `middle_initial`
  - `birthday`, `address`, `phone`
  - `student_id`, `course`, `year_level`, `faculty`
  - `status` (active, graduated, dropped)
  - `role` (admin, accounting, student) - Enum
  - `profile_picture`, `email_verified_at`, `remember_token`
  - `notification_preferences` (JSON)
  - `created_at`, `updated_at` (standard timestamps)

- **Relationships:**
  ```php
  - hasOne(Student)
  - hasOne(Account)
  - hasMany(Transaction)
  - hasMany(WorkflowApproval) // as approver
  ```

- **Key Methods:**
  - `getNameAttribute()` - returns formatted name
  - `getValidationRules()` - reusable validation rules

#### Database Schema
- **Primary Table:** `users`
- **Key Migration Files:**
  - `0001_01_01_000000_create_users_table.php` - Base schema (id, name, email, password, created_at, updated_at)
  - `2025_09_06_024253_add_role_to_users_table.php` - Adds role column as enum
  - `2025_10_17_033309_add_name_fields_to_users_table.php` - Adds last_name, first_name, middle_initial
  - `2025_10_20_150250_drop_name_column_from_users_table.php` - Removes old name column
  - `2025_10_29_124343_add_faculty_column_to_users.php` - Adds faculty for staff
  - `2026_02_13_154406_add_notification_preferences_to_users.php` - Adds JSON notification preferences

#### Authentication System
- **Config:** `config/auth.php`
- **Guard:** `web` (default)
- **Provider:** Uses `User` model
- **Password Broker:** `users` (standard)
- **Features:**
  - Standard Laravel authentication (login, registration, password reset)
  - Email verification support
  - Remember me token
  - No custom authentication middleware

#### Authorization System
- **Middleware:** `app/Http/Middleware/RoleMiddleware.php`
- **Implementation:**
  ```php
  if (!$user || !in_array($user->role->value, $roles)) {
      abort(403, 'Unauthorized action.');
  }
  ```
- **Policies:**
  - `StudentFeePolicy` - Controls student fee access
  - `WorkflowApprovalPolicy` - Controls workflow approvals
  - **Missing:** UserPolicy, AdminPolicy, comprehensive authorization
- **Usage:** Routes use `role:admin,accounting` middleware

#### Routes & Access Control
- **Route Protection Pattern:**
  ```php
  Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->group(function () {
      // Admin routes
  });
  ```
- **Protected Routes:**
  - `student/*` - role:student
  - `student-fees/*` - role:admin,accounting
  - `admin/dashboard` - role:admin
  - `payments/*` - role:admin,accounting
  - `subjects/*` - role:admin,accounting

#### Controllers & Services
- **User Management:**
  - `UserController` handles CRUD for all users
  - Basic validation: name, email, password, role
  - No admin-specific logic

- **Admin-Specific:**
  - `AdminDashboardController` - Minimal (just renders dashboard)
  - No admin management service
  - No admin creation/update workflows

- **Support Services:**
  - `AccountService` - Account operations
  - `FeeAssignmentService` - Fee management
  - `PaymentCarryoverService` - Payment carryover
  - `WorkflowService` - Workflow management
  - **Missing:** AdminService

#### Frontend - User Forms
- **Profile Editor:** `resources/js/pages/settings/Profile.vue`
  - Handles both student and staff profile updates
  - Split name fields (last_name, first_name, middle_initial)
  - Role-aware field display (student_id for students, faculty for staff)
  - Status field management
  - Profile picture upload
  - **Missing:** Terms & Conditions acceptance UI

---

### 1.2 How Admin Users Currently Exist

#### Current State
1. **Role-Based Only:** Admin is a role in the role enum, not a special entity
   ```php
   enum UserRoleEnum: string {
       case ADMIN = 'admin';
       case ACCOUNTING = 'accounting';
       case STUDENT = 'student';
   }
   ```

2. **Route Access:** Protected via simple role middleware
3. **No Special Fields:** Admin users are just User records with `role='admin'`
4. **Limited Functionality:**
   - Can access `/admin/dashboard`
   - No inherited permissions
   - No audit tracking of admin actions
   - No terms acceptance

#### Issues & Gaps
- ❌ No way to track who created/updated an admin account
- ❌ No Terms & Conditions acceptance verification
- ❌ No granular permissions (everything is granted or denied by role)
- ❌ No admin-specific attributes (e.g., department, permissions)
- ❌ No audit trail of admin actions
- ❌ No distinction between types of admins (e.g., super admin, manager)
- ❌ No admin deactivation without deleting user record

---

## PART 2: RECOMMENDED ADMIN ARCHITECTURE

### 2.1 Design Decision: Which Approach?

#### Option A: Role on Users Table (RECOMMENDED) ✅
**Implementation:** Keep admin as a role with additional specialized fields

**Pros:**
- Minimal database changes
- Leverages existing RBAC system
- Easier user lifecycle management
- Single user table for all users
- Easier to query and manage
- Existing relationships continue to work

**Cons:**
- Some fields may be null for non-admins
- Slightly less separation of concerns

#### Option B: Separate Admin Table ❌
**Implementation:** Create separate `admins` table with 1:1 relationship

**Pros:**
- Clean separation
- No nullable fields

**Cons:**
- Complex joins required
- Breaking change to existing relationships
- Harder to convert users to admin
- Duplicate user data

#### Option C: Polymorphic Role System ❌
**Implementation:** Use polymorphic relationships for different role types

**Pros:**
- Very flexible for future role types

**Cons:**
- Overly complex for current needs
- Performance overhead
- Difficult to query

### 2.2 SELECTED APPROACH: Enhanced Role-Based with Admin Fields

**Architecture:**
- Keep role-based access control (admin, accounting, student)
- Add admin-specific fields to users table
- Add permission system for fine-grained control
- Implement UserPolicy and AdminPolicy
- Create AdminService for admin operations

**Why This Approach:**
1. Minimal disruption to existing system
2. Leverages proven RBAC middleware
3. Allows gradual feature addition
4. Maintains backward compatibility
5. Supports audit logging and tracking
6. Easy to query and manage

---

## PART 3: REQUIRED CHANGES

### 3.1 Database Layer

#### A. New Migration: Add Admin-Specific Fields

**File:** `database/migrations/2026_02_18_000000_add_admin_fields_to_users_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Admin-specific fields
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('terms_accepted_at')->nullable()->after('is_active');
            $table->json('permissions')->nullable()->after('terms_accepted_at');
            $table->string('department')->nullable()->after('permissions');
            $table->enum('admin_type', ['super', 'manager', 'operator'])->nullable()->after('department');
            
            // Audit fields
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('admin_type');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
            $table->timestamp('last_login_at')->nullable()->after('updated_by');
            
            // Indexing for performance
            $table->index('role');
            $table->index('is_active');
            $table->index('admin_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeignIdFor('created_by');
            $table->dropForeignIdFor('updated_by');
            
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['admin_type']);
            
            $table->dropColumn([
                'is_active',
                'terms_accepted_at',
                'permissions',
                'department',
                'admin_type',
                'created_by',
                'updated_by',
                'last_login_at',
            ]);
        });
    }
};
```

#### B. Alternative: Separate Permissions Table

**File:** `database/migrations/2026_02_18_000001_create_admin_permissions_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Admin permissions lookup table
        Schema::create('admin_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g., 'manage_users', 'manage_fees'
            $table->string('description');
            $table->string('category'); // e.g., 'admin', 'accounting', 'system'
            $table->timestamps();
        });

        // Admin role permissions pivot table
        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->enum('admin_type', ['super', 'manager', 'operator']);
            $table->foreignId('permission_id')->constrained('admin_permissions')->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['admin_type', 'permission_id']);
        });

        // User-specific permission overrides
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('admin_permissions')->cascadeOnDelete();
            $table->boolean('granted')->default(true);
            $table->timestamp('granted_at');
            $table->foreignId('granted_by')->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->unique(['user_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('admin_role_permissions');
        Schema::dropIfExists('admin_permissions');
    }
};
```

#### C. Seeder: Default Admin Permissions

**File:** `database/seeders/AdminPermissionSeeder.php`

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define all available permissions
        $permissions = [
            // User Management
            ['key' => 'manage_users', 'description' => 'Create, edit, delete users', 'category' => 'admin'],
            ['key' => 'manage_admins', 'description' => 'Manage admin accounts', 'category' => 'admin'],
            ['key' => 'view_users', 'description' => 'View all users', 'category' => 'admin'],
            
            // Fee Management
            ['key' => 'manage_fees', 'description' => 'Create and assign fees', 'category' => 'accounting'],
            ['key' => 'approve_payments', 'description' => 'Approve student payments', 'category' => 'accounting'],
            ['key' => 'view_payments', 'description' => 'View all payments', 'category' => 'accounting'],
            
            // System
            ['key' => 'manage_workflows', 'description' => 'Create and manage workflows', 'category' => 'system'],
            ['key' => 'view_audit_logs', 'description' => 'View system audit logs', 'category' => 'system'],
            ['key' => 'system_settings', 'description' => 'Manage system settings', 'category' => 'system'],
        ];

        DB::table('admin_permissions')->insertOrIgnore($permissions);

        // Define permissions by admin type
        $permissions = DB::table('admin_permissions')->get();
        $permissionMap = $permissions->keyBy('key');

        $rolePermissions = [
            'super' => ['manage_users', 'manage_admins', 'view_users', 'manage_fees', 'approve_payments', 'view_payments', 'manage_workflows', 'view_audit_logs', 'system_settings'],
            'manager' => ['manage_admins', 'view_users', 'manage_fees', 'approve_payments', 'view_payments', 'manage_workflows', 'view_audit_logs'],
            'operator' => ['view_users', 'approve_payments', 'view_payments', 'view_audit_logs'],
        ];

        foreach ($rolePermissions as $adminType => $perms) {
            foreach ($perms as $permKey) {
                if (isset($permissionMap[$permKey])) {
                    DB::table('admin_role_permissions')->insertOrIgnore([
                        'admin_type' => $adminType,
                        'permission_id' => $permissionMap[$permKey]->id,
                    ]);
                }
            }
        }
    }
}
```

---

### 3.2 Backend Layer

#### A. Updated User Model

**File:** `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Enums\UserRoleEnum;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_GRADUATED = 'graduated';
    const STATUS_DROPPED = 'dropped';

    // Admin types
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
     */
    public function getNameAttribute(): string
    {
        $mi = $this->middle_initial ? ' ' . strtoupper($this->middle_initial) . '.' : '';
        return "{$this->last_name}, {$this->first_name}{$mi}";
    }

    /**
     * Get the user's full name (alternative format).
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
        // This is a simple check; extend with permission table if needed
        if ($this->isAdmin()) {
            // Define permissions by admin type
            $permissionsByType = [
                self::ADMIN_TYPE_SUPER => ['*'], // all permissions
                self::ADMIN_TYPE_MANAGER => ['manage_fees', 'manage_workflows', 'approve_payments', 'view_users'],
                self::ADMIN_TYPE_OPERATOR => ['approve_payments', 'view_users'],
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
            'terms_accepted' => 'required|accepted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Update user activity timestamp
     */
    public function recordLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }
}
```

#### B. User Policy (Authorization)

**File:** `app/Policies/UserPolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    /**
     * Determine whether the user can view the user.
     */
    public function view(User $user, User $model): bool
    {
        // Admins can view any user
        if ($user->isAdmin() && $user->is_active) {
            return true;
        }

        // Users can view their own profile
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->is_active && $user->hasPermission('manage_users');
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        // Users can update their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Admins can update other users
        if ($user->isAdmin() && $user->is_active && $user->hasPermission('manage_users')) {
            // Cannot edit users with higher privileges
            return $user->admin_type === User::ADMIN_TYPE_SUPER || 
                   $user->admin_type !== User::ADMIN_TYPE_OPERATOR;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the user.
     */
    public function delete(User $user, User $model): bool
    {
        // Cannot delete self
        if ($user->id === $model->id) {
            return false;
        }

        // Only super admins can delete
        return $user->isAdmin() && 
               $user->is_active && 
               $user->isSuperAdmin() && 
               $user->hasPermission('manage_users');
    }

    /**
     * Determine whether the user can restore the user.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->is_active;
    }

    /**
     * Determine whether the user can permanently delete the user.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->isSuperAdmin() && $user->is_active;
    }

    /**
     * Determine if user can manage admin accounts
     */
    public function manageAdmins(User $user): bool
    {
        return $user->isSuperAdmin() && $user->is_active && $user->hasPermission('manage_admins');
    }

    /**
     * Determine if user can accept terms
     */
    public function acceptTerms(User $user, User $model): bool
    {
        return $user->id === $model->id;
    }
}
```

#### C. AdminService

**File:** `app/Services/AdminService.php`

```php
<?php

namespace App\Services;

use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;

class AdminService
{
    /**
     * Create a new admin user
     */
    public function createAdmin(array $data, ?User $createdBy = null): User
    {
        // Validate input
        $validated = $this->validateAdminData($data);

        // Create the admin user
        $admin = User::create([
            'last_name' => $validated['last_name'],
            'first_name' => $validated['first_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => $validated['admin_type'],
            'department' => $validated['department'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $createdBy?->id,
            'updated_by' => $createdBy?->id,
        ]);

        // If terms were accepted in the data, record it
        if ($validated['terms_accepted'] ?? false) {
            $admin->acceptTerms();
        }

        return $admin;
    }

    /**
     * Update an admin user
     */
    public function updateAdmin(User $admin, array $data, ?User $updatedBy = null): User
    {
        $validated = $this->validateAdminData($data, $admin->id);

        $updateData = [
            'last_name' => $validated['last_name'] ?? $admin->last_name,
            'first_name' => $validated['first_name'] ?? $admin->first_name,
            'middle_initial' => $validated['middle_initial'] ?? $admin->middle_initial,
            'admin_type' => $validated['admin_type'] ?? $admin->admin_type,
            'department' => $validated['department'] ?? $admin->department,
            'is_active' => $validated['is_active'] ?? $admin->is_active,
            'updated_by' => $updatedBy?->id,
        ];

        // Only update password if provided
        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $admin->update($updateData);

        return $admin->refresh();
    }

    /**
     * Deactivate an admin user
     */
    public function deactivateAdmin(User $admin): bool
    {
        if (!$admin->isAdmin()) {
            throw new \InvalidArgumentException('User is not an admin');
        }

        if ($admin->admin_type === User::ADMIN_TYPE_SUPER && $admin->is_active) {
            // Check if there are other active super admins
            $activeSuperAdmins = User::admins()
                ->where('admin_type', User::ADMIN_TYPE_SUPER)
                ->where('is_active', true)
                ->count();

            if ($activeSuperAdmins <= 1) {
                throw new \InvalidArgumentException('Cannot deactivate the only super admin');
            }
        }

        return $admin->update(['is_active' => false]);
    }

    /**
     * Check if admin can perform an action
     */
    public function hasPermission(User $admin, string $permission): bool
    {
        if (!$admin->isAdmin()) {
            return false;
        }

        return $admin->hasPermission($permission);
    }

    /**
     * Get all active admins
     */
    public function getActiveAdmins()
    {
        return User::admins()
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get admins by type
     */
    public function getAdminsByType(string $type)
    {
        return User::admins()
            ->where('admin_type', $type)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Validate admin data
     */
    private function validateAdminData(array $data, ?int $userId = null): array
    {
        $rules = User::getAdminValidationRules($userId);

        return validator($data, $rules)->validate();
    }

    /**
     * Get admin statistics
     */
    public function getAdminStats(): array
    {
        $admins = User::admins()->where('is_active', true)->get();

        return [
            'total_active_admins' => $admins->count(),
            'super_admins' => $admins->where('admin_type', User::ADMIN_TYPE_SUPER)->count(),
            'managers' => $admins->where('admin_type', User::ADMIN_TYPE_MANAGER)->count(),
            'operators' => $admins->where('admin_type', User::ADMIN_TYPE_OPERATOR)->count(),
            'terms_accepted' => $admins->where('terms_accepted_at', '!=', null)->count(),
            'last_login_avg_days' => $this->calculateAverageLastLogin($admins),
        ];
    }

    /**
     * Calculate average days since last login
     */
    private function calculateAverageLastLogin($admins): ?int
    {
        $loggedInAdmins = $admins->filter(fn($a) => $a->last_login_at !== null);

        if ($loggedInAdmins->isEmpty()) {
            return null;
        }

        $totalDays = $loggedInAdmins->sum(fn($a) => now()->diffInDays($a->last_login_at));

        return (int) ($totalDays / $loggedInAdmins->count());
    }

    /**
     * Log admin action (for audit trail)
     */
    public function logAdminAction(User $admin, string $action, array $details = []): void
    {
        // Implement audit logging if needed
        // Example: AdminAuditLog::create([
        //     'admin_id' => $admin->id,
        //     'action' => $action,
        //     'details' => $details,
        //     'ip_address' => request()->ip(),
        //     'user_agent' => request()->userAgent(),
        // ]);
    }
}
```

#### D. AdminController Updates

**File:** `app/Http/Controllers/AdminController.php` (Create New)

```php
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

        try {
            $admin = $this->adminService->createAdmin(
                $request->all(),
                $request->user()
            );

            return redirect()->route('admin.users.show', $admin)
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

            return redirect()->route('admin.users.show', $user)
                ->with('success', 'Admin updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }
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
            return back()->withErrors(['error' => $e->getMessage()]);
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

        $user->update(['is_active' => true]);

        return back()->with('success', 'Admin reactivated successfully!');
    }
}
```

#### E. Update Routes

**File:** `routes/web.php` (Add to admin routes section)

```php
// ADMIN USER MANAGEMENT
Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->group(function () {
    Route::resource('users', AdminController::class);
    Route::post('users/{user}/deactivate', [AdminController::class, 'deactivate'])->name('admin.users.deactivate');
    Route::post('users/{user}/reactivate', [AdminController::class, 'reactivate'])->name('admin.users.reactivate');
});
```

#### F. Register Policy in AuthServiceProvider

**File:** `app/Providers/AuthServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Policies\StudentFeePolicy;
use App\Models\WorkflowApproval;
use App\Policies\WorkflowApprovalPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        WorkflowApproval::class => WorkflowApprovalPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
```

---

### 3.3 Frontend Layer

#### A. Admin Terms & Conditions Component

**File:** `resources/js/components/TermsAcceptance.vue`

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { useForm } from '@inertiajs/vue3'

interface Props {
  adminId?: number
  onTermsAccepted?: () => void
}

withDefaults(defineProps<Props>(), {})

const accepted = ref(false)
const termsVisible = ref(false)

const form = useForm({
  terms_accepted: false,
})

const submitTerms = () => {
  form.post(route('admin.terms.accept'), {
    onSuccess: () => {
      accepted.value = true
      emit('termsAccepted')
    },
  })
}

const emit = defineEmits<{
  termsAccepted: []
}>()
</script>

<template>
  <div class="space-y-4">
    <div v-if="!accepted" class="border rounded-lg p-4 bg-amber-50">
      <h3 class="font-semibold text-lg mb-4">Terms & Conditions</h3>
      
      <div v-if="!termsVisible" class="mb-4">
        <p class="text-sm text-gray-600 mb-4">
          As an administrator, you must accept the terms and conditions before proceeding.
        </p>
        <Button 
          @click="termsVisible = true"
          variant="outline"
          class="w-full"
        >
          Read Terms & Conditions
        </Button>
      </div>

      <div v-else class="bg-white border rounded p-4 mb-4 max-h-64 overflow-y-auto">
        <h4 class="font-semibold mb-2">Administrator Terms & Conditions</h4>
        <div class="text-sm space-y-2 text-gray-700">
          <p><strong>1. Responsibility:</strong> Administrators are responsible for maintaining system integrity.</p>
          <p><strong>2. Data Security:</strong> All user data must be handled securely and confidentially.</p>
          <p><strong>3. Audit Trail:</strong> All admin actions are logged and auditable.</p>
          <p><strong>4. Compliance:</strong> Administrators must comply with all system policies.</p>
          <p><strong>5. Account Security:</strong> You are responsible for protecting your login credentials.</p>
          <p><strong>6. Misuse:</strong> Unauthorized access or misuse of admin privileges is prohibited.</p>
          <!-- Add more terms as needed -->
        </div>
      </div>

      <div class="flex items-start space-x-2 mb-4">
        <Checkbox 
          id="terms"
          v-model:checked="form.terms_accepted"
        />
        <label for="terms" class="text-sm cursor-pointer">
          I accept the terms and conditions
        </label>
      </div>

      <Button 
        @click="submitTerms"
        :disabled="!form.terms_accepted || form.processing"
        class="w-full"
      >
        {{ form.processing ? 'Processing...' : 'Accept Terms' }}
      </Button>
    </div>

    <div v-else class="border-l-4 border-green-500 bg-green-50 p-4">
      <p class="text-green-800">
        ✓ Terms and conditions accepted on {{ new Date().toLocaleDateString() }}
      </p>
    </div>
  </div>
</template>
```

#### B. Admin User Create/Edit Form

**File:** `resources/js/pages/Admin/Users/Form.vue`

```vue
<script setup lang="ts">
import { reactive } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import InputError from '@/components/InputError.vue'
import TermsAcceptance from '@/components/TermsAcceptance.vue'

interface Props {
  admin?: any
  isEditing?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  isEditing: false,
})

const form = useForm({
  last_name: props.admin?.last_name ?? '',
  first_name: props.admin?.first_name ?? '',
  middle_initial: props.admin?.middle_initial ?? '',
  email: props.admin?.email ?? '',
  password: '',
  password_confirmation: '',
  admin_type: props.admin?.admin_type ?? 'manager',
  department: props.admin?.department ?? '',
  is_active: props.admin?.is_active ?? true,
  terms_accepted: props.admin?.terms_accepted_at ? true : false,
})

const submit = () => {
  if (props.isEditing) {
    form.post(route('admin.users.update', props.admin.id), {
      method: 'put',
    })
  } else {
    form.post(route('admin.users.store'))
  }
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <Label for="last_name">Last Name *</Label>
        <Input
          id="last_name"
          v-model="form.last_name"
          type="text"
          required
        />
        <InputError :message="form.errors.last_name" />
      </div>

      <div>
        <Label for="first_name">First Name *</Label>
        <Input
          id="first_name"
          v-model="form.first_name"
          type="text"
          required
        />
        <InputError :message="form.errors.first_name" />
      </div>

      <div>
        <Label for="middle_initial">Middle Initial</Label>
        <Input
          id="middle_initial"
          v-model="form.middle_initial"
          type="text"
          maxlength="1"
          class="uppercase"
        />
        <InputError :message="form.errors.middle_initial" />
      </div>
    </div>

    <div>
      <Label for="email">Email Address *</Label>
      <Input
        id="email"
        v-model="form.email"
        type="email"
        required
      />
      <InputError :message="form.errors.email" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <Label for="password">{{ isEditing ? 'Password (leave blank to keep current)' : 'Password' }} *</Label>
        <Input
          id="password"
          v-model="form.password"
          type="password"
          :required="!isEditing"
        />
        <InputError :message="form.errors.password" />
      </div>

      <div>
        <Label for="password_confirmation">Confirm Password *</Label>
        <Input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          :required="!isEditing"
        />
        <InputError :message="form.errors.password_confirmation" />
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <Label for="admin_type">Admin Type *</Label>
        <select
          id="admin_type"
          v-model="form.admin_type"
          class="w-full px-3 py-2 border rounded-lg"
          required
        >
          <option value="super">Super Admin</option>
          <option value="manager">Manager</option>
          <option value="operator">Operator</option>
        </select>
        <InputError :message="form.errors.admin_type" />
      </div>

      <div>
        <Label for="department">Department</Label>
        <Input
          id="department"
          v-model="form.department"
          type="text"
          placeholder="e.g., Finance, Operations"
        />
        <InputError :message="form.errors.department" />
      </div>
    </div>

    <div v-if="!isEditing" class="mt-6">
      <TermsAcceptance />
    </div>

    <div class="flex space-x-4 pt-4">
      <Button type="submit" :disabled="form.processing">
        {{ form.processing ? 'Saving...' : isEditing ? 'Update Admin' : 'Create Admin' }}
      </Button>
      <Button
        type="button"
        variant="outline"
        @click="$router.back()"
      >
        Cancel
      </Button>
    </div>
  </form>
</template>
```

#### C. Profiles Form Update (Existing)

**File:** `resources/js/pages/settings/Profile.vue` (Update to support Terms)

Add this field to the profile form:

```vue
<div v-if="isAdmin" class="border rounded-lg p-4 bg-blue-50">
  <h3 class="font-semibold mb-2">Admin Information</h3>
  
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
      <Label>Admin Type: <strong>{{ user.admin_type || 'Not specified' }}</strong></Label>
    </div>
    <div>
      <Label>Department: <strong>{{ user.department || 'Not specified' }}</strong></Label>
    </div>
  </div>

  <div v-if="user.terms_accepted_at" class="text-sm text-green-700 bg-green-100 p-2 rounded">
    ✓ Terms accepted on {{ new Date(user.terms_accepted_at).toLocaleDateString() }}
  </div>
  <div v-else class="text-sm text-amber-700 bg-amber-100 p-2 rounded">
    ⚠ Terms not yet accepted
  </div>
</div>
```

---

## PART 4: FILE-BY-FILE MODIFICATION LIST

| File | Type | Change | Details |
|------|------|--------|---------|
| `database/migrations/2026_02_18_000000_add_admin_fields_to_users_table.php` | NEW | Create migration | Add admin-specific fields: is_active, terms_accepted_at, permissions, department, admin_type, created_by, updated_by, last_login_at |
| `database/migrations/2026_02_18_000001_create_admin_permissions_table.php` | NEW | Create migration | Create permission table system (optional, if using granular permissions) |
| `database/seeders/AdminPermissionSeeder.php` | NEW | Create seeder | Seed default admin permissions and admin-type mappings |
| `app/Models/User.php` | MODIFY | Update model | Add new fields to fillable, casts, relationships; add scopes and helper methods for admin functionality |
| `app/Policies/UserPolicy.php` | NEW | Create policy | Authorization rules for user operations |
| `app/Services/AdminService.php` | NEW | Create service | Business logic for admin operations (create, update, deactivate) |
| `app/Http/Controllers/AdminController.php` | NEW | Create controller | CRUD operations for admin users |
| `app/Providers/AuthServiceProvider.php` | MODIFY | Update provider | Register UserPolicy |
| `routes/web.php` | MODIFY | Update routes | Add admin user management routes |
| `resources/js/components/TermsAcceptance.vue` | NEW | Create component | Terms & Conditions acceptance UI |
| `resources/js/pages/Admin/Users/Index.vue` | NEW | Create page | List all admin users |
| `resources/js/pages/Admin/Users/Create.vue` | NEW | Create page | Create new admin user form |
| `resources/js/pages/Admin/Users/Edit.vue` | NEW | Create page | Edit admin user form |
| `resources/js/pages/Admin/Users/Show.vue` | NEW | Create page | View admin user details |
| `resources/js/pages/Admin/Users/Form.vue` | NEW | Create component | Reusable admin form (Create/Edit) |
| `resources/js/pages/settings/Profile.vue` | MODIFY | Update form | Add admin information display section and terms status |
| `app/Enums/AdminTypeEnum.php` | NEW | Create enum | Enum for admin types (super, manager, operator) |

---

## PART 5: DATA MIGRATION STRATEGY

### 5.1 Handling Existing Users

#### Current Admins (If Any)
1. **Identify:** Query users where `role = 'admin'`
2. **Default Values:**
   ```sql
   UPDATE users 
   SET 
       is_active = true,
       admin_type = 'manager',
       terms_accepted_at = NOW(),
       created_by = NULL,
       updated_by = NULL
   WHERE role = 'admin';
   ```
3. **Reasoning:** Assume existing admins have consented (can be verified later)

#### Non-Admin Users
- **No Changes:** Student and accounting users remain unaffected
- **Nullable Fields:** New fields default to NULL for non-admins

### 5.2 Rollback Plan

```php
// In migration down() method:
- Drop new columns
- If using permission tables, drop those too
- Existing data is preserved if needed
```

### 5.3 Incremental Migration

If migrating a live system:

1. **Deploy migration** (with default values)
2. **Deploy code** (backward compatible - old fields optional)
3. **Require terms** (enforce terms_accepted_at in next release)
4. **Monitor** audit fields for issues before fully relying on them

---

## PART 6: TESTING PLAN

### 6.1 Unit Tests

**File:** `tests/Unit/Models/UserAdminTest.php`

```php
<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;

class UserAdminTest extends TestCase
{
    /** @test */
    public function admin_can_be_created(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
        ]);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($admin->isSuperAdmin());
    }

    /** @test */
    public function super_admin_is_identified_correctly(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
        ]);

        $this->assertTrue($admin->isSuperAdmin());
    }

    /** @test */
    public function admin_can_accept_terms(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'terms_accepted_at' => null,
        ]);

        $this->assertFalse($admin->hasAcceptedTerms());

        $admin->acceptTerms();

        $this->assertTrue($admin->hasAcceptedTerms());
        $this->assertNotNull($admin->terms_accepted_at);
    }

    /** @test */
    public function admin_permissions_are_checked(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
        ]);

        $manager = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
        ]);

        $operator = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'operator',
        ]);

        $this->assertTrue($superAdmin->hasPermission('manage_users'));
        $this->assertTrue($manager->hasPermission('manage_fees'));
        $this->assertFalse($operator->hasPermission('manage_users'));
    }

    /** @test */
    public function inactive_admin_has_no_permissions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
            'is_active' => false,
        ]);

        $this->assertFalse($admin->hasPermission('manage_users'));
    }
}
```

### 6.2 Feature Tests

**File:** `tests/Feature/Admin/AdminControllerTest.php`

```php
<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;

class AdminControllerTest extends TestCase
{
    private User $superAdmin;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function super_admin_can_list_admins(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.users.index'));

        $response->assertStatus(200);
    }

    /** @test */
    public function manager_cannot_create_admins(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('admin.users.create'));

        $response->assertStatus(403);
    }

    /** @test */
    public function super_admin_can_create_admin(): void
    {
        $data = [
            'last_name' => 'Test',
            'first_name' => 'Admin',
            'email' => 'test@example.com',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'admin_type' => 'manager',
            'terms_accepted' => true,
        ];

        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.store'), $data);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => UserRoleEnum::ADMIN->value,
        ]);
    }

    /** @test */
    public function cannot_deactivate_only_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.users.deactivate', $this->superAdmin));

        $response->assertSessionHasErrors();
    }
}
```

### 6.3 Authorization Tests

**File:** `tests/Feature/Policies/UserPolicyTest.php`

```php
<?php

namespace Tests\Feature\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;

class UserPolicyTest extends TestCase
{
    /** @test */
    public function user_can_view_own_profile(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->can('view', $user));
    }

    /** @test */
    public function user_cannot_view_others_profile(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertFalse($user->can('view', $other));
    }

    /** @test */
    public function admin_can_view_any_user(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
        ]);
        $user = User::factory()->create();

        $this->assertTrue($admin->can('view', $user));
    }

    /** @test */
    public function only_super_admin_can_delete(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
        ]);
        $manager = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'manager',
        ]);
        $target = User::factory()->create();

        $this->assertTrue($superAdmin->can('delete', $target));
        $this->assertFalse($manager->can('delete', $target));
    }
}
```

### 6.4 Database Tests

**File:** `tests/Feature/Admin/AdminDatabaseTest.php`

```php
<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRoleEnum;

class AdminDatabaseTest extends TestCase
{
    /** @test */
    public function admin_created_by_and_updated_by_are_tracked(): void
    {
        $creator = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'admin_type' => 'super',
        ]);

        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'created_by' => $creator->id,
            'updated_by' => $creator->id,
        ]);

        $this->assertEquals($creator->id, $admin->created_by);
        $this->assertEquals($creator->id, $admin->updated_by);
        $this->assertNotNull($admin->createdByUser);
    }

    /** @test */
    public function last_login_is_recorded(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'last_login_at' => null,
        ]);

        $this->assertNull($admin->last_login_at);

        $admin->recordLastLogin();

        $this->assertNotNull($admin->refresh()->last_login_at);
    }
}
```

### 6.5 Edge Cases

- ✅ Cannot create admin without accepting terms
- ✅ Cannot delete own admin account
- ✅ Cannot deactivate only super admin
- ✅ Inactive admins have no permissions
- ✅ Password changes require confirmation
- ✅ Email must be unique
- ✅ Audit fields are immutable (created_by/updated_by shouldn't be manually changed)
- ✅ Permissions are RO after assignment (except by admin service)

---

## PART 7: SECURITY CONSIDERATIONS

### 7.1 Password Security

✅ **Hashing:** Passwords MUST use Laravel's Hash facade (uses bcrypt)
```php
$user->password = Hash::make($request->password); // ✓ Correct
$user->password = $request->password; // ✗ WRONG
```

✅ **Validation:** Enforce password strength
```php
'password' => ['required', 'min:8', 'confirmed', Rules\Password::defaults()],
```

✅ **Change Detection:** Log password changes
```php
if ($user->password !== $originalPassword) {
    // Log or notify
}
```

### 7.2 Role Escalation Prevention

✅ **Authorization Checks:** Always use policies
```php
// ✓ Correct - uses policy
$this->authorize('update', $user);

// ✗ WRONG - no authorization check
User::find($id)->update($data);
```

✅ **Cannot Self-Promote:** Check current user permissions
```php
if ($newAdminType > $currentAdmin->admin_type) {
    // Reject if trying to escalate to higher role
}
```

✅ **Audit Trail:** Log all privilege changes
```php
AdminAuditLog::create([
    'admin_id' => auth()->id(),
    'action' => 'admin_type_changed',
    'old_value' => $oldType,
    'new_value' => $newType,
]);
```

### 7.3 Terms Acceptance Immutability

❌ **Should NOT be modifiable:** Once accepted, terms_accepted_at should not be manually changed
```php
// ✗ WRONG - allows tampering
$admin->update(['terms_accepted_at' => null]);

// ✓ Correct - use dedicated method
$admin->acceptTerms(); // Sets to now()
// Or rejectTerms() if needed
```

✅ **Enforcement:**
```php
protected $fillable = [
    // terms_accepted_at should NOT be in fillable
    // Only set via acceptTerms() method
];
```

✅ **Verify on Critical Actions:**
```php
if (!$admin->hasAcceptedTerms()) {
    abort(403, 'Admin must accept terms first');
}
```

### 7.4 Audit Logging

⚠️ **Implementation Required:** Log all admin actions

**Recommended Fields:**
- `admin_id` - Who performed the action
- `action` - What action (create, update, delete, etc.)
- `target_type` - Resource type (User, Fee, Payment, etc.)
- `target_id` - ID of affected resource
- `changes` - JSON of what changed
- `ip_address` - Source IP
- `user_agent` - Browser info
- `timestamp` - When it happened

**Create Model:**
```php
class AdminAuditLog extends Model
{
    protected $casts = [
        'changes' => 'json',
    ];
}
```

**Log in Service:**
```php
AdminAuditLog::create([
    'admin_id' => auth()->id(),
    'action' => 'user_created',
    'target_type' => 'User',
    'target_id' => $admin->id,
    'changes' => [
        'email' => $admin->email,
        'role' => UserRoleEnum::ADMIN->value,
    ],
    'ip_address' => request()->ip(),
    'user_agent' => request()->userAgent(),
]);
```

### 7.5 Deactivation vs Deletion

✅ **Prefer Deactivation:**
- Set `is_active = false` instead of deleting
- Preserves audit trail and relationships
- Easier to reactivate if needed
- Maintains referential integrity

✅ **Prevent Data Loss:**
```php
public function destroyAdminRecords(User $admin)
{
    // Never delete - only deactivate
    $admin->update(['is_active' => false]);
    
    // Reassign owned records
    WorkflowApproval::where('approver_id', $admin->id)
        ->update(['approver_id' => null]);
}
```

### 7.6 Session & Login Security

⚠️ **Enhance Login:**
```php
// In AuthenticatedSessionController
public function store(LoginRequest $request)
{
    $request->authenticate();

    $user = auth()->user();
    
    // Record last login
    if ($user->isAdmin()) {
        $user->recordLastLogin();
    }

    $request->session()->regenerate();
    return redirect(route('dashboard'));
}
```

⚠️ **IP Validation (Optional):**
```php
// Detect suspicious logins
if ($admin->last_login_at && $admin->last_login_ip !== request()->ip()) {
    // Send verification email
}
```

### 7.7 Rate Limiting

✅ **Apply to Admin Routes:**
```php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('users', [AdminController::class, 'store']);
});
```

### 7.8 CSRF & Mass Assignment

✅ **CSRF Protection:** Inertia/Laravel handle automatically
✅ **Mass Assignment:** Control with `$fillable` array

---

## PART 8: STEP-BY-STEP IMPLEMENTATION CHECKLIST

### Phase 1: Database Setup
- [ ] Create migration: `2026_02_18_000000_add_admin_fields_to_users_table.php`
- [ ] (Optional) Create migration: `2026_02_18_000001_create_admin_permissions_table.php`
- [ ] Create seeder: `AdminPermissionSeeder.php`
- [ ] Run `php artisan migrate`
- [ ] Run `php artisan db:seed --class=AdminPermissionSeeder`
- [ ] Verify new columns exist: `php artisan tinker -> DB::table('users')->first()`

### Phase 2: Backend Models & Services
- [ ] Create `app/Enums/AdminTypeEnum.php` (optional, improves type safety)
- [ ] Update `app/Models/User.php` with new fields, relationships, scopes, helpers
- [ ] Create `app/Services/AdminService.php` with business logic
- [ ] Create `app/Policies/UserPolicy.php` for authorization
- [ ] Update `app/Providers/AuthServiceProvider.php` to register policy
- [ ] Test models in tinker:
  - [ ] `User::where('role', 'admin')->first()->hasAcceptedTerms()`
  - [ ] `User::admins()->count()`
  - [ ] Test permission checks

### Phase 3: Controllers & Routes
- [ ] Create `app/Http/Controllers/AdminController.php`
- [ ] Add routes to `routes/web.php`:
  - [ ] `admin.users.index`
  - [ ] `admin.users.create`
  - [ ] `admin.users.store`
  - [ ] `admin.users.show`
  - [ ] `admin.users.edit`
  - [ ] `admin.users.update`
  - [ ] `admin.users.deactivate`
  - [ ] `admin.users.reactivate`
- [ ] Verify routes: `php artisan route:list | grep admin.users`

### Phase 4: Frontend Components
- [ ] Create `resources/js/components/TermsAcceptance.vue`
- [ ] Create `resources/js/pages/Admin/Users/Form.vue`
- [ ] Create `resources/js/pages/Admin/Users/Index.vue`
- [ ] Create `resources/js/pages/Admin/Users/Create.vue`
- [ ] Create `resources/js/pages/Admin/Users/Edit.vue`
- [ ] Create `resources/js/pages/Admin/Users/Show.vue`
- [ ] Update `resources/js/pages/settings/Profile.vue` to show admin info
- [ ] Build frontend: `npm run build`

### Phase 5: Testing
- [ ] Write unit tests: `tests/Unit/Models/UserAdminTest.php`
- [ ] Write feature tests: `tests/Feature/Admin/AdminControllerTest.php`
- [ ] Write policy tests: `tests/Feature/Policies/UserPolicyTest.php`
- [ ] Write database tests: `tests/Feature/Admin/AdminDatabaseTest.php`
- [ ] Run tests: `php artisan test`
- [ ] Ensure 100% pass rate

### Phase 6: Manual Testing
- [ ] Login as super admin
- [ ] Create new admin user (test all admin types)
- [ ] Verify terms acceptance is required
- [ ] Update admin user
- [ ] Deactivate admin (verify cannot deactivate last super admin)
- [ ] Reactivate admin
- [ ] Verify permission checks work
- [ ] Test as different admin types (manager, operator)
- [ ] Verify audit fields (`created_by`, `updated_by`)
- [ ] Check `last_login_at` is recorded

### Phase 7: Security Audit
- [ ] Verify all admin routes require authentication
- [ ] Verify all admin actions are authorized via policy
- [ ] Check password is properly hashed
- [ ] Verify terms_accepted_at cannot be manually set
- [ ] Check created_by/updated_by audit fields work
- [ ] Test that inactive admins cannot perform actions
- [ ] Verify rate limiting on sensitive routes
- [ ] Check CSRF protection is active

### Phase 8: Documentation & Rollout
- [ ] Document new fields in API documentation
- [ ] Add admin management to user guide
- [ ] Create sample admin creation script/seeder
- [ ] Test migration rollback
- [ ] Prepare deployment plan
- [ ] Get stakeholder approval
- [ ] Deploy to staging
- [ ] Test in staging
- [ ] Deploy to production
- [ ] Monitor for errors
- [ ] Document lessons learned

---

## PART 9: QUICK REFERENCE

### Common Commands

```bash
# Run migrations
php artisan migrate

# Seed permissions
php artisan db:seed --class=AdminPermissionSeeder

# Create super admin (in tinker)
php artisan tinker
> $user = User::create(['email' => 'admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'admin_type' => 'super', 'is_active' => true])
> $user->acceptTerms()

# List all admins
User::where('role', 'admin')->get()

# Find inactive admins
User::where('role', 'admin')->where('is_active', false)->get()

# Test permissions
$admin = User::where('role', 'admin')->first()
$admin->hasPermission('manage_users')
```

### Key Files by Purpose

**Authentication:** `config/auth.php`
**Authorization:** `app/Policies/UserPolicy.php`
**Business Logic:** `app/Services/AdminService.php`
**Database:** `database/migrations/2026_02_18_*.php`
**Frontend:** `resources/js/pages/Admin/Users/*`
**Routes:** `routes/web.php` (admin middleware group)

---

## PART 10: NEXT STEPS AFTER IMPLEMENTATION

1. **Audit Logging:** Implement AdminAuditLog model to track all admin actions
2. **Admin Dashboard:** Create comprehensive admin dashboard with statistics
3. **Permission Management UI:** Build UI to manage granular permissions
4. **Activity Log View:** Allow admins to view audit trail
5. **Role Templates:** Create preset roles with common permission sets
6. **Two-Factor Authentication:** Add 2FA for admin accounts
7. **API Documentation:** Document admin endpoints for API consumption
8. **Admin Notifications:** Alert super admins of important events
9. **Bulk Admin Operations:** Add bulk create/update capabilities
10. **Admin Reports:** Generate reports on admin actions and system access

---

**Document Version:** 1.0  
**Last Updated:** February 18, 2026  
**Status:** Ready for Implementation
