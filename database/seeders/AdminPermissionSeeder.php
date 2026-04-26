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
        // Clear existing data (disable foreign key checks to allow truncation)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        DB::table('user_permissions')->truncate();
        DB::table('admin_role_permissions')->truncate();
        DB::table('admin_permissions')->truncate();
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Define all available permissions
        $permissions = [
            // User Management
            ['key' => 'manage_users', 'description' => 'Create, edit, delete users', 'category' => 'admin'],
            ['key' => 'manage_admins', 'description' => 'Manage admin accounts', 'category' => 'admin'],
            ['key' => 'view_users', 'description' => 'View all users', 'category' => 'admin'],
            ['key' => 'deactivate_users', 'description' => 'Deactivate user accounts', 'category' => 'admin'],

            // Fee Management
            ['key' => 'manage_fees', 'description' => 'Create and assign fees', 'category' => 'accounting'],
            ['key' => 'approve_payments', 'description' => 'Approve student payments', 'category' => 'accounting'],
            ['key' => 'view_payments', 'description' => 'View all payments', 'category' => 'accounting'],
            ['key' => 'manage_accounts', 'description' => 'Manage student accounts', 'category' => 'accounting'],

            // Workflow Management
            ['key' => 'manage_workflows', 'description' => 'Create and manage workflows', 'category' => 'system'],
            ['key' => 'approve_workflows', 'description' => 'Approve workflow instances', 'category' => 'system'],

            // System
            ['key' => 'view_audit_logs', 'description' => 'View system audit logs', 'category' => 'system'],
            ['key' => 'system_settings', 'description' => 'Manage system settings', 'category' => 'system'],
            ['key' => 'view_reports', 'description' => 'View system reports', 'category' => 'system'],
        ];

        DB::table('admin_permissions')->insert($permissions);

        // Define permissions by admin type
        $permissions = DB::table('admin_permissions')->get();
        $permissionMap = $permissions->keyBy('key');

        $rolePermissions = [
            'super' => [
                'manage_users', 'manage_admins', 'view_users', 'deactivate_users',
                'manage_fees', 'approve_payments', 'view_payments', 'manage_accounts',
                'manage_workflows', 'approve_workflows',
                'view_audit_logs', 'system_settings', 'view_reports',
            ],
            'manager' => [
                'manage_admins', 'view_users', 'deactivate_users',
                'manage_fees', 'approve_payments', 'view_payments', 'manage_accounts',
                'manage_workflows', 'approve_workflows',
                'view_audit_logs', 'view_reports',
            ],
            'operator' => [
                'view_users',
                'approve_payments', 'view_payments',
                'approve_workflows',
                'view_audit_logs',
            ],
        ];

        foreach ($rolePermissions as $adminType => $perms) {
            foreach ($perms as $permKey) {
                if (isset($permissionMap[$permKey])) {
                    DB::table('admin_role_permissions')->insert([
                        'admin_type' => $adminType,
                        'permission_id' => $permissionMap[$permKey]->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
