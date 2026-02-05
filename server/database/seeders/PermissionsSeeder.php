<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    public function run()
    {
        $permissions = $this->getPermissions();

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                [
                    'label' => $permission['label'],
                    'description' => $permission['description'],
                    'category' => $permission['category'],
                ]
            );
        }

        $this->createDefaultRoles();
    }

    private function getPermissions()
    {
        return [
            // Dashboard Permissions
            ['name' => 'dashboard.view', 'label' => 'View Dashboard', 'description' => 'Access to view admin dashboard', 'category' => 'dashboard'],
            ['name' => 'dashboard.view_own', 'label' => 'View Own Dashboard', 'description' => 'Access to view personal dashboard', 'category' => 'dashboard'],

            // User/Faculty Management Permissions
            ['name' => 'faculty.view', 'label' => 'View Faculty', 'description' => 'Can view faculty members in department', 'category' => 'faculty'],
            ['name' => 'faculty.view_all', 'label' => 'View All Faculty', 'description' => 'Can view all faculty members across departments', 'category' => 'faculty'],
            ['name' => 'faculty.add', 'label' => 'Add Faculty', 'description' => 'Can invite/add new faculty members', 'category' => 'faculty'],
            ['name' => 'faculty.edit', 'label' => 'Edit Faculty', 'description' => 'Can edit faculty member details', 'category' => 'faculty'],
            ['name' => 'faculty.archive', 'label' => 'Archive Faculty', 'description' => 'Can archive/deactivate faculty members', 'category' => 'faculty'],
            ['name' => 'faculty.unarchive', 'label' => 'Unarchive Faculty', 'description' => 'Can restore archived faculty members', 'category' => 'faculty'],
            ['name' => 'faculty.remove_permanently', 'label' => 'Permanently Remove Faculty', 'description' => 'Can permanently delete faculty members', 'category' => 'faculty'],
            ['name' => 'faculty.export', 'label' => 'Export Faculty', 'description' => 'Can export faculty list', 'category' => 'faculty'],

            // Role & Permission Management
            ['name' => 'roles.view', 'label' => 'View Roles', 'description' => 'Can view roles list', 'category' => 'roles'],
            ['name' => 'roles.manage', 'label' => 'Manage Roles', 'description' => 'Can create and edit roles', 'category' => 'roles'],
            ['name' => 'roles.assign', 'label' => 'Assign Roles', 'description' => 'Can assign roles to users', 'category' => 'roles'],
            ['name' => 'roles.permissions', 'label' => 'Manage Role Permissions', 'description' => 'Can modify role permissions', 'category' => 'roles'],
            ['name' => 'roles.delete', 'label' => 'Delete Roles', 'description' => 'Can delete roles', 'category' => 'roles'],

            // Activity Logs
            ['name' => 'activity.view', 'label' => 'View Own Activity', 'description' => 'Can view own activity logs', 'category' => 'activity'],
            ['name' => 'activity.view_department', 'label' => 'View Department Activity', 'description' => 'Can view department activity logs', 'category' => 'activity'],
            ['name' => 'activity.view_all', 'label' => 'View All Activity', 'description' => 'Can view all system activity logs', 'category' => 'activity'],
            ['name' => 'activity.export', 'label' => 'Export Activity Logs', 'description' => 'Can export activity logs', 'category' => 'activity'],
            ['name' => 'activity.clear', 'label' => 'Clear Activity Logs', 'description' => 'Can clear activity logs', 'category' => 'activity'],

            // Memo Management
            ['name' => 'memo.view', 'label' => 'View Memos', 'description' => 'Can view memos', 'category' => 'memo'],
            ['name' => 'memo.view_all', 'label' => 'View All Memos', 'description' => 'Can view all memos across system', 'category' => 'memo'],
            ['name' => 'memo.create', 'label' => 'Create Memos', 'description' => 'Can create new memos', 'category' => 'memo'],
            ['name' => 'memo.edit', 'label' => 'Edit Memos', 'description' => 'Can edit memos', 'category' => 'memo'],
            ['name' => 'memo.delete', 'label' => 'Delete Memos', 'description' => 'Can delete memos', 'category' => 'memo'],
            ['name' => 'memo.archive', 'label' => 'Archive Memos', 'description' => 'Can archive memos', 'category' => 'memo'],
            ['name' => 'memo.unarchive', 'label' => 'Unarchive Memos', 'description' => 'Can restore archived memos', 'category' => 'memo'],
            ['name' => 'memo.send', 'label' => 'Send Memos', 'description' => 'Can send memos to recipients', 'category' => 'memo'],
            ['name' => 'memo.acknowledge', 'label' => 'Acknowledge Memos', 'description' => 'Can acknowledge received memos', 'category' => 'memo'],
            ['name' => 'memo.priority', 'label' => 'Set Memo Priority', 'description' => 'Can set memo priority levels', 'category' => 'memo'],
            ['name' => 'memo.template', 'label' => 'Manage Templates', 'description' => 'Can create and edit memo templates', 'category' => 'memo'],

            // Calendar Permissions
            ['name' => 'calendar.view', 'label' => 'View Calendar', 'description' => 'Can view calendar', 'category' => 'calendar'],
            ['name' => 'calendar.add_event', 'label' => 'Add Events', 'description' => 'Can add calendar events', 'category' => 'calendar'],
            ['name' => 'calendar.edit_event', 'label' => 'Edit Events', 'description' => 'Can edit calendar events', 'category' => 'calendar'],
            ['name' => 'calendar.delete_event', 'label' => 'Delete Events', 'description' => 'Can delete calendar events', 'category' => 'calendar'],
            ['name' => 'calendar.archive_event', 'label' => 'Archive Events', 'description' => 'Can archive calendar events', 'category' => 'calendar'],
            ['name' => 'calendar.sync', 'label' => 'Sync Calendar', 'description' => 'Can sync with Google Calendar', 'category' => 'calendar'],

            // Reports & Analytics
            ['name' => 'reports.view', 'label' => 'View Reports', 'description' => 'Can view reports and analytics', 'category' => 'reports'],
            ['name' => 'reports.export', 'label' => 'Export Reports', 'description' => 'Can export reports', 'category' => 'reports'],
            ['name' => 'reports.view_analytics', 'label' => 'View Analytics', 'description' => 'Can view system analytics', 'category' => 'reports'],
            ['name' => 'reports.view_memos', 'label' => 'View Memo Reports', 'description' => 'Can view memo statistics', 'category' => 'reports'],
            ['name' => 'reports.view_users', 'label' => 'View User Reports', 'description' => 'Can view user activity reports', 'category' => 'reports'],

            // Archive Permissions
            ['name' => 'archive.view', 'label' => 'View Archive', 'description' => 'Can view archived items', 'category' => 'archive'],
            ['name' => 'archive.restore', 'label' => 'Restore Items', 'description' => 'Can restore archived items', 'category' => 'archive'],
            ['name' => 'archive.delete_permanently', 'label' => 'Permanently Delete', 'description' => 'Can permanently delete archived items', 'category' => 'archive'],
            ['name' => 'archive.restore_all', 'label' => 'Restore All', 'description' => 'Can restore all archived items at once', 'category' => 'archive'],

            // Settings Permissions
            ['name' => 'settings.view', 'label' => 'View Settings', 'description' => 'Can view system settings', 'category' => 'settings'],
            ['name' => 'settings.edit', 'label' => 'Edit Settings', 'description' => 'Can modify system settings', 'category' => 'settings'],
            ['name' => 'settings.lock_duration', 'label' => 'Manage Lock Duration', 'description' => 'Can configure lock timeout settings', 'category' => 'settings'],
            ['name' => 'settings.departments', 'label' => 'Manage Departments', 'description' => 'Can manage departments', 'category' => 'settings'],
            ['name' => 'settings.system', 'label' => 'System Settings', 'description' => 'Can access advanced system settings', 'category' => 'settings'],

            // Sidebar Navigation Permissions
            ['name' => 'nav.dashboard', 'label' => 'Dashboard Navigation', 'description' => 'Can access Dashboard sidebar', 'category' => 'navigation'],
            ['name' => 'nav.users', 'label' => 'Users Navigation', 'description' => 'Can access Users sidebar', 'category' => 'navigation'],
            ['name' => 'nav.faculty', 'label' => 'Faculty Navigation', 'description' => 'Can access Faculty sidebar', 'category' => 'navigation'],
            ['name' => 'nav.memos', 'label' => 'Memos Navigation', 'description' => 'Can access Memos sidebar', 'category' => 'navigation'],
            ['name' => 'nav.calendar', 'label' => 'Calendar Navigation', 'description' => 'Can access Calendar sidebar', 'category' => 'navigation'],
            ['name' => 'nav.reports', 'label' => 'Reports Navigation', 'description' => 'Can access Reports sidebar', 'category' => 'navigation'],
            ['name' => 'nav.activity_logs', 'label' => 'Activity Logs Navigation', 'description' => 'Can access Activity Logs sidebar', 'category' => 'navigation'],
            ['name' => 'nav.archive', 'label' => 'Archive Navigation', 'description' => 'Can access Archive sidebar', 'category' => 'navigation'],
            ['name' => 'nav.roles', 'label' => 'Roles Navigation', 'description' => 'Can access Roles sidebar', 'category' => 'navigation'],
            ['name' => 'nav.settings', 'label' => 'Settings Navigation', 'description' => 'Can access Settings sidebar', 'category' => 'navigation'],

            // Admin Specific
            ['name' => 'admin.super', 'label' => 'Super Admin', 'description' => 'Full system access', 'category' => 'admin'],
            ['name' => 'admin.users', 'label' => 'Manage System Users', 'description' => 'Can manage admin users', 'category' => 'admin'],
        ];
    }

    private function createDefaultRoles()
    {
        $allPermissionNames = Permission::pluck('name')->toArray();

        // ADMIN ROLE - Full Access
        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            [
                'label' => 'Administrator',
                'description' => 'System Administrator with full access',
                'status' => 'active',
                'permission_ids' => $allPermissionNames,
            ]
        );

        // SECRETARY ROLE - Department-level access
        $secretaryPermissions = [
            // Dashboard
            'dashboard.view_own',
            // Faculty
            'faculty.view',
            'faculty.view_all',
            'faculty.add',
            'faculty.edit',
            'faculty.archive',
            'faculty.unarchive',
            // Memos
            'memo.view',
            'memo.view_all',
            'memo.create',
            'memo.edit',
            'memo.archive',
            'memo.send',
            'memo.acknowledge',
            'memo.priority',
            'memo.template',
            // Calendar
            'calendar.view',
            'calendar.add_event',
            'calendar.edit_event',
            'calendar.delete_event',
            'calendar.archive_event',
            'calendar.sync',
            // Archive
            'archive.view',
            'archive.restore',
            // Settings
            'settings.view',
            // Navigation
            'nav.dashboard',
            'nav.faculty',
            'nav.memos',
            'nav.calendar',
            'nav.archive',
            'nav.settings',
        ];

        $secretaryRole = Role::updateOrCreate(
            ['name' => 'secretary'],
            [
                'label' => 'Department Secretary',
                'description' => 'Department Secretary with department-level access',
                'status' => 'active',
                'permission_ids' => $secretaryPermissions,
            ]
        );

        // FACULTY ROLE - Basic access (only what belongs to faculty)
        $facultyPermissions = [
            // Dashboard
            'dashboard.view_own',
            // Memos
            'memo.view',
            'memo.acknowledge',
            // Calendar
            'calendar.view',
            'calendar.add_event',
            'calendar.edit_event',
            'calendar.delete_event',
            // Archive
            'archive.view',
            // Settings
            'settings.view',
            // Navigation
            'nav.dashboard',
            'nav.memos',
            'nav.calendar',
            'nav.archive',
            'nav.settings',
        ];

        $facultyRole = Role::updateOrCreate(
            ['name' => 'faculty'],
            [
                'label' => 'Faculty Member',
                'description' => 'Faculty Member with basic access',
                'status' => 'active',
                'permission_ids' => $facultyPermissions,
            ]
        );

        $this->command->info('Default roles created successfully:');
        $this->command->info('- Admin: ' . count($allPermissionNames) . ' permissions');
        $this->command->info('- Secretary: ' . count($secretaryPermissions) . ' permissions');
        $this->command->info('- Faculty: ' . count($facultyPermissions) . ' permissions');
    }
}
