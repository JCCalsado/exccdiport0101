<?php

namespace App\Services;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminService
{
    /**
     * Create a new admin user
     */
    public function createAdmin(array $data, int|User|null $createdBy = null): User
    {
        // Resolve to User model if int given
        if (is_int($createdBy)) {
            $createdBy = User::find($createdBy);
        }

        // Auto-populate password_confirmation if not provided (service-layer calls)
        if (isset($data['password']) && ! isset($data['password_confirmation'])) {
            $data['password_confirmation'] = $data['password'];
        }

        // Validate input
        $validated = $this->validateAdminData($data);

        // Create the admin user with role based on department
        $department = $validated['department'] ?? 'Administrator';
        $role       = $department === 'Accounting' ? UserRoleEnum::ACCOUNTING : UserRoleEnum::ADMIN;

        $admin = User::create([
            'last_name'      => $validated['last_name'],
            'first_name'     => $validated['first_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'email'          => $validated['email'],
            'password'       => Hash::make($validated['password']),
            'role'           => $role,
            'department'     => $department,
            'is_active'      => $validated['is_active'] ?? true,
            'updated_by'     => $createdBy?->id,
        ]);

        // Set created_by directly (not mass-assignable to protect audit immutability)
        $admin->forceFill(['created_by' => $createdBy?->id])->save();

        // Always record terms acceptance on creation
        $admin->acceptTerms();

        return $admin;
    }

    /**
     * Update an admin user.
     *
     * Fixed: email was validated and returned in $validated but never included
     * in $updateData, so the database column was never touched on update.
     *
     * Also fixed: when email changes, email_verified_at is nulled to force
     * re-verification on the new address.
     */
    public function updateAdmin(User $admin, array $data, int|User|null $updatedBy = null): User
    {
        // Resolve to User model if int given
        if (is_int($updatedBy)) {
            $updatedBy = User::find($updatedBy);
        }

        // Auto-populate password_confirmation if not provided
        if (isset($data['password']) && ! isset($data['password_confirmation'])) {
            $data['password_confirmation'] = $data['password'];
        }

        $validated = $this->validateAdminUpdateData($data, $admin->id);

        // Update department and sync role accordingly
        $department = array_key_exists('department', $validated) ? $validated['department'] : $admin->department;
        $role       = $department === 'Accounting' ? UserRoleEnum::ACCOUNTING : UserRoleEnum::ADMIN;

        $updateData = [
            'last_name'      => $validated['last_name']      ?? $admin->last_name,
            'first_name'     => $validated['first_name']     ?? $admin->first_name,
            'middle_initial' => $validated['middle_initial'] ?? $admin->middle_initial,
            'email'          => $validated['email']          ?? $admin->email,  // ← was missing
            'department'     => $department,
            'role'           => $role,
            'is_active'      => $validated['is_active']      ?? $admin->is_active,
            'updated_by'     => $updatedBy?->id,
        ];

        // If the email address was changed, force re-verification on the new address
        if ($updateData['email'] !== $admin->email) {
            $updateData['email_verified_at'] = null;
        }

        // Only update password if a new one was provided
        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $admin->update($updateData);

        return $admin->refresh();
    }

    /**
     * Deactivate an admin or accounting user.
     *
     * Prevents self-deactivation (must have another admin perform the action).
     */
    public function deactivateAdmin(User $admin, ?User $performedBy = null): bool
    {
        if (! in_array($admin->department, ['Administrator', 'Accounting'])) {
            throw new \InvalidArgumentException('User is not an admin or accounting staff');
        }

        if ($performedBy && $performedBy->id === $admin->id) {
            throw new \InvalidArgumentException('You cannot deactivate your own account. Ask another admin to deactivate you.');
        }

        return $admin->update(['is_active' => false]);
    }

    /**
     * Reactivate an admin or accounting user.
     */
    public function reactivateAdmin(User $admin): bool
    {
        if (! in_array($admin->department, ['Administrator', 'Accounting'])) {
            throw new \InvalidArgumentException('User is not an admin or accounting staff');
        }

        return $admin->update(['is_active' => true]);
    }

    /**
     * Check if admin can perform an action.
     */
    public function hasPermission(User $admin, string $permission): bool
    {
        if (! $admin->isAdmin()) {
            return false;
        }

        return $admin->hasPermission($permission);
    }

    /**
     * Get all active admins.
     */
    public function getActiveAdmins()
    {
        return User::admins()
            ->where('is_active', true)
            ->with(['createdByUser', 'updatedByUser'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Validate admin data for creation (all fields required).
     */
    private function validateAdminData(array $data, ?int $userId = null): array
    {
        $rules = User::getAdminValidationRules($userId);

        return validator($data, $rules)->validate();
    }

    /**
     * Validate admin data for updates (all fields optional with sometimes).
     */
    private function validateAdminUpdateData(array $data, int $userId): array
    {
        $rules = User::getAdminValidationRules($userId);

        // Wrap every rule with 'sometimes' so only supplied fields are validated
        $updateRules = [];
        foreach ($rules as $field => $rule) {
            $ruleString = is_array($rule) ? implode('|', $rule) : $rule;
            // Replace 'required' with 'sometimes' but keep everything else
            $ruleString = preg_replace('/\brequired\b\|?/', '', $ruleString);
            $ruleString = ltrim($ruleString, '|');
            $updateRules[$field] = 'sometimes|' . $ruleString;
        }

        return validator($data, $updateRules)->validate();
    }

    /**
     * Get admin statistics.
     */
    public function getAdminStats(): array
    {
        $allUsers      = User::whereIn('department', ['Administrator', 'Accounting'])->get();
        $allAdmins     = $allUsers->where('department', 'Administrator');
        $allAccounting = $allUsers->where('department', 'Accounting');

        $activeAdmins     = $allAdmins->where('is_active', true);
        $activeAccounting = $allAccounting->where('is_active', true);

        return [
            'total_admins'            => $allAdmins->count(),
            'total_active_admins'     => $activeAdmins->count(),
            'total_accounting'        => $allAccounting->count(),
            'total_active_accounting' => $activeAccounting->count(),
            'terms_accepted'          => $allAdmins->filter(fn ($a) => $a->terms_accepted_at !== null)->count(),
            'last_login_avg_days'     => $this->calculateAverageLastLogin($activeAdmins),
        ];
    }

    /**
     * Calculate average days since last login.
     */
    private function calculateAverageLastLogin($admins): ?int
    {
        $loggedInAdmins = $admins->filter(fn ($a) => $a->last_login_at !== null);

        if ($loggedInAdmins->isEmpty()) {
            return null;
        }

        $totalDays = $loggedInAdmins->sum(fn ($a) => now()->diffInDays($a->last_login_at));

        return (int) ($totalDays / $loggedInAdmins->count());
    }

    /**
     * Log admin action (for audit trail).
     */
    public function logAdminAction(int|User $admin, string $action, string $model = '', int $modelId = 0, array $details = []): void
    {
        // Implement audit logging if needed
    }
}