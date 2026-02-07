<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

class RBACSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Define Permissions
        $permissionsData = [
            // Memos Tab
            ['name' => 'memo.create', 'label' => 'Create Memos', 'description' => 'Create new memos', 'category' => 'memo'],
            ['name' => 'memo.send', 'label' => 'Send Memos', 'description' => 'Send memos to recipients', 'category' => 'memo'],
            ['name' => 'memo.archive', 'label' => 'Archive Memos', 'description' => 'Move memos to archive', 'category' => 'memo'],
            ['name' => 'memo.view', 'label' => 'View Memos', 'description' => 'View all memos', 'category' => 'memo'],
            ['name' => 'memo.unarchive', 'label' => 'Unarchive Memos', 'description' => 'Restore memos from archive', 'category' => 'memo'],
            ['name' => 'memo.remove_permanently', 'label' => 'Permanent Delete', 'description' => 'Remove memos permanently', 'category' => 'memo'],
            ['name' => 'memo.approve', 'label' => 'Approve Memos', 'description' => 'Approve memos submitted for review', 'category' => 'memo'],
            
            // Faculty Tab
            ['name' => 'faculty.add', 'label' => 'Add Faculty', 'description' => 'Add/Invite faculty members', 'category' => 'faculty'],
            ['name' => 'faculty.edit', 'label' => 'Edit Faculty', 'description' => 'Edit faculty details', 'category' => 'faculty'],
            ['name' => 'faculty.archive', 'label' => 'Archive Faculty', 'description' => 'Archive faculty accounts', 'category' => 'faculty'],
            ['name' => 'faculty.view', 'label' => 'View Faculty', 'description' => 'View active and archived faculty', 'category' => 'faculty'],
            ['name' => 'faculty.unarchive', 'label' => 'Unarchive Faculty', 'description' => 'Restore faculty accounts', 'category' => 'faculty'],
            
            // Archive Tab
            ['name' => 'archive.unarchive_memo', 'label' => 'Unarchive Memos', 'description' => 'Unarchive memos from the archive tab', 'category' => 'archive'],
            ['name' => 'archive.unarchive_calendar', 'label' => 'Unarchive Events', 'description' => 'Unarchive calendar events', 'category' => 'archive'],
            ['name' => 'archive.remove_permanently', 'label' => 'Permanent Delete', 'description' => 'Remove archived items permanently', 'category' => 'archive'],
            
            // Calendar Tab
            ['name' => 'calendar.add_event', 'label' => 'Add Event', 'description' => 'Create calendar events', 'category' => 'calendar'],
            ['name' => 'calendar.edit_event', 'label' => 'Edit Event', 'description' => 'Modified calendar events', 'category' => 'calendar'],
            ['name' => 'calendar.archive_event', 'label' => 'Archive Event', 'description' => 'Archive calendar events', 'category' => 'calendar'],
            
            // Theme
            ['name' => 'theme.select', 'label' => 'Select Theme', 'description' => 'Select theme color from allowed palette', 'category' => 'settings'],

            // Activity Logs
            ['name' => 'activity.view_all', 'label' => 'View All Activities', 'description' => 'View system-wide activity logs', 'category' => 'activity'],
            ['name' => 'activity.view_department', 'label' => 'View Department Activities', 'description' => 'View activities within the department', 'category' => 'activity'],

            // Reports & Analytics
            ['name' => 'reports.view', 'label' => 'View Reports', 'description' => 'Access reports and analytics dashboard', 'category' => 'reports'],
            ['name' => 'reports.export', 'label' => 'Export Reports', 'description' => 'Export reports to PDF, Excel, or CSV', 'category' => 'reports'],

            // Department Management
            ['name' => 'department.manage', 'label' => 'Manage Departments', 'description' => 'Create, edit, and delete departments', 'category' => 'memo'],

            // Template Management
            ['name' => 'template.manage', 'label' => 'Manage Templates', 'description' => 'Create and edit memo templates', 'category' => 'memo'],

            // Signature Management
            ['name' => 'signature.manage', 'label' => 'Manage Signatures', 'description' => 'Create and edit memo signatures', 'category' => 'memo'],
        ];

        $permissions = [];
        foreach ($permissionsData as $data) {
            $permissions[$data['name']] = Permission::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }

        // 2. Define Roles
        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            ['label' => 'System Administrator', 'description' => 'Full control over the system', 'status' => 'active']
        );

        $secretaryRole = Role::updateOrCreate(
            ['name' => 'secretary'],
            ['label' => 'Department Secretary', 'description' => 'Manage department memos and faculty', 'status' => 'active']
        );

        $facultyRole = Role::updateOrCreate(
            ['name' => 'faculty'],
            ['label' => 'Faculty Member', 'description' => 'View and manage personal memos and events', 'status' => 'active']
        );

        // 3. Assign Permissions to Roles
        
        // Admin gets everything
        $allPermissionNames = array_keys($permissions);
        $adminRole->update(['permission_ids' => $allPermissionNames]);

        // Secretary Permissions
        $secretaryPermissions = [
            'memo.create', 'memo.send', 'memo.archive', 'memo.view', 'memo.unarchive', 'memo.remove_permanently',
            'faculty.add', 'faculty.edit', 'faculty.archive', 'faculty.view', 'faculty.unarchive',
            'archive.unarchive_memo', 'archive.unarchive_calendar', 'archive.remove_permanently',
            'calendar.add_event', 'calendar.edit_event', 'calendar.archive_event',
            'theme.select', 'activity.view_department', 'template.manage'
        ];
        $secretaryRole->update(['permission_ids' => $secretaryPermissions]);

        // Faculty Permissions
        $facultyPermissions = [
            'memo.view', 'memo.archive',
            'archive.unarchive_memo', 'archive.remove_permanently',
            'calendar.add_event', 'calendar.edit_event'
        ];
        $facultyRole->update(['permission_ids' => $facultyPermissions]);

        // 4. Update Existing Users to use role_id and sync strings
        foreach (User::all() as $user) {
            $roleName = $user->role; // Get current string role
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $user->update(['role_id' => $role->id]);
            }
        }
    }

    private function getIds($names, $permissions)
    {
        return array_map(fn($name) => $permissions[$name]->id, $names);
    }
}
