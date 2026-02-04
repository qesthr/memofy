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
            ['name' => 'memo.create', 'label' => 'Create Memos', 'description' => 'Create new memos'],
            ['name' => 'memo.send', 'label' => 'Send Memos', 'description' => 'Send memos to recipients'],
            ['name' => 'memo.archive', 'label' => 'Archive Memos', 'description' => 'Move memos to archive'],
            ['name' => 'memo.view', 'label' => 'View Memos', 'description' => 'View all memos'],
            ['name' => 'memo.unarchive', 'label' => 'Unarchive Memos', 'description' => 'Restore memos from archive'],
            ['name' => 'memo.remove_permanently', 'label' => 'Permanent Delete', 'description' => 'Remove memos permanently'],
            
            // Faculty Tab
            ['name' => 'faculty.add', 'label' => 'Add Faculty', 'description' => 'Add/Invite faculty members'],
            ['name' => 'faculty.edit', 'label' => 'Edit Faculty', 'description' => 'Edit faculty details'],
            ['name' => 'faculty.archive', 'label' => 'Archive Faculty', 'description' => 'Archive faculty accounts'],
            ['name' => 'faculty.view', 'label' => 'View Faculty', 'description' => 'View active and archived faculty'],
            ['name' => 'faculty.unarchive', 'label' => 'Unarchive Faculty', 'description' => 'Restore faculty accounts'],
            
            // Archive Tab
            ['name' => 'archive.unarchive_memo', 'label' => 'Unarchive Memos', 'description' => 'Unarchive memos from the archive tab'],
            ['name' => 'archive.unarchive_calendar', 'label' => 'Unarchive Events', 'description' => 'Unarchive calendar events'],
            ['name' => 'archive.remove_permanently', 'label' => 'Permanent Delete', 'description' => 'Remove archived items permanently'],
            
            // Calendar Tab
            ['name' => 'calendar.add_event', 'label' => 'Add Event', 'description' => 'Create calendar events'],
            ['name' => 'calendar.edit_event', 'label' => 'Edit Event', 'description' => 'Modified calendar events'],
            ['name' => 'calendar.archive_event', 'label' => 'Archive Event', 'description' => 'Archive calendar events'],
            
            // Theme
            ['name' => 'theme.select', 'label' => 'Select Theme', 'description' => 'Select theme color from allowed palette'],

            // Activity Logs
            ['name' => 'activity.view_all', 'label' => 'View All Activities', 'description' => 'View system-wide activity logs'],
            ['name' => 'activity.view_department', 'label' => 'View Department Activities', 'description' => 'View activities within the department'],

            // Department Management
            ['name' => 'department.manage', 'label' => 'Manage Departments', 'description' => 'Create, edit, and delete departments'],

            // Template Management
            ['name' => 'template.manage', 'label' => 'Manage Templates', 'description' => 'Create and edit memo templates'],
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
        $allPermissionIds = array_values(array_map(fn($p) => $p->id, $permissions));
        $adminRole->update(['permission_ids' => $allPermissionIds]);

        // Secretary Permissions
        $secretaryPermissions = [
            'memo.create', 'memo.send', 'memo.archive', 'memo.view', 'memo.unarchive', 'memo.remove_permanently',
            'faculty.add', 'faculty.edit', 'faculty.archive', 'faculty.view', 'faculty.unarchive',
            'archive.unarchive_memo', 'archive.unarchive_calendar', 'archive.remove_permanently',
            'calendar.add_event', 'calendar.edit_event', 'calendar.archive_event',
            'theme.select', 'activity.view_department', 'template.manage'
        ];
        $secretaryRole->update(['permission_ids' => $this->getIds($secretaryPermissions, $permissions)]);

        // Faculty Permissions
        $facultyPermissions = [
            'memo.view', 'memo.archive',
            'archive.unarchive_memo', 'archive.remove_permanently',
            'calendar.add_event', 'calendar.edit_event'
        ];
        $facultyRole->update(['permission_ids' => $this->getIds($facultyPermissions, $permissions)]);

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
