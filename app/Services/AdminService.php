<?php

namespace App\Services;

use App\Models\User;
use App\Enums\UserRoleEnum;
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
        if (isset($data['password']) && !isset($data['password_confirmation'])) {
            $data['password_confirmation'] = $data['password'];
        }

        // Validate input
        $validated = $this->validateAdminData($data);

        // Create the admin user
        $admin = User::create([
            'last_name'      => $validated['last_name'],
            'first_name'     => $validated['first_name'],
            'middle_initial' => $validated['middle_initial'] ?? null,
            'email'          => $validated['email'],
            'password'       => Hash::make($validated['password']),
            'role'           => UserRoleEnum::ADMIN,
            'admin_type'     => $validated['admin_type'],
            'department'     => $validated['department'] ?? null,
            'is_active'      => $validated['is_active'] ?? true,
            'created_by'     => $createdBy?->id,
            'updated_by'     => $createdBy?->id,
        ]);

        // Always record terms acceptance on creation
        $admin->acceptTerms();

        return $admin;
    }

    /**
     * Update an admin user
     */
    public function updateAdmin(User $admin, array $data, int|User|null $updatedBy = null): User
    {
        // Resolve to User model if int given
        if (is_int($updatedBy)) {
            $updatedBy = User::find($updatedBy);
        }

        // Auto-populate password_confirmation if not provided
        if (isset($data['password']) && !isset($data['password_confirmation'])) {
            $data['password_confirmation'] = $data['password'];
        }

        $validated = $this->validateAdminUpdateData($data, $admin->id);

        $updateData = [
            'last_name'      => $validated['last_name']    ?? $admin->last_name,
            'first_name'     => $validated['first_name']   ?? $admin->first_name,
            'middle_initial' => $validated['middle_initial'] ?? $admin->middle_initial,
            'admin_type'     => $validated['admin_type']   ?? $admin->admin_type,
            'department'     => array_key_exists('department', $validated) ? $validated['department'] : $admin->department,
            'is_active'      => $validated['is_active']    ?? $admin->is_active,
            'updated_by'     => $updatedBy?->id,
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
            $activeSuperAdmins = User::admins()
                ->where('admin_type', User::ADMIN_TYPE_SUPER)
                ->where('is_active', true)
                ->count();

            if ($activeSuperAdmins <= 1) {
                throw new \InvalidArgumentException('Cannot deactivate the last super admin');
            }
        }

        return $admin->update(['is_active' => false]);
    }

    /**
     * Reactivate an admin user
     */
    public function reactivateAdmin(User $admin): bool
    {
        if (!$admin->isAdmin()) {
            throw new \InvalidArgumentException('User is not an admin');
        }

        return $admin->update(['is_active' => true]);
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
            ->with(['createdByUser', 'updatedByUser'])
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
            ->with(['createdByUser', 'updatedByUser'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Validate admin data for creation (all fields required)
     */
    private function validateAdminData(array $data, ?int $userId = null): array
    {
        $rules = User::getAdminValidationRules($userId);

        return validator($data, $rules)->validate();
    }

    /**
     * Validate admin data for updates (all fields optional with sometimes)
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
     * Get admin statistics
     */
    public function getAdminStats(): array
    {
        $admins = User::admins()->where('is_active', true)->get();

        return [
            'total_admins'        => $admins->count(),
            'total_active_admins' => $admins->count(),
            'super_admins'        => $admins->where('admin_type', User::ADMIN_TYPE_SUPER)->count(),
            'managers'            => $admins->where('admin_type', User::ADMIN_TYPE_MANAGER)->count(),
            'operators'           => $admins->where('admin_type', User::ADMIN_TYPE_OPERATOR)->count(),
            'terms_accepted'      => $admins->filter(fn($a) => $a->terms_accepted_at !== null)->count(),
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
    public function logAdminAction(int|User $admin, string $action, string $model = '', int $modelId = 0, array $details = []): void
    {
        // Implement audit logging if needed
    }
}