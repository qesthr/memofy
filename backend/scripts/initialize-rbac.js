/**
 * Initialize RBAC System
 *
 * This script initializes the RBAC system and verifies all roles and permissions.
 * Run with: node backend/scripts/initialize-rbac.js
 */

require('dotenv').config();
const rbacService = require('../services/rbacService');

async function initializeRBAC() {
    try {
        console.log('🔐 Initializing RBAC System...\n');

        // Initialize RBAC
        await rbacService.initializeRBAC();
        console.log('✅ RBAC system initialized\n');

        // Get all roles
        const roles = rbacService.getAllRoles();
        console.log('📋 Available Roles:');
        roles.forEach(role => {
            console.log(`   - ${role}`);
        });
        console.log('');

        // Get all permissions
        const permissions = rbacService.getAllPermissions();
        console.log('🔑 Available Permissions:');
        Object.entries(permissions).forEach(([key, description]) => {
            console.log(`   - ${key}: ${description}`);
        });
        console.log('');

        // Test role permissions
        console.log('🧪 Testing Role Permissions:\n');

        const testRoles = ['faculty', 'secretary', 'admin', 'superadmin'];

        for (const role of testRoles) {
            const user = { role };
            const rolePermissions = await rbacService.getRolePermissions(role);

            console.log(`   ${role.toUpperCase()}:`);
            console.log(`     Total Permissions: ${rolePermissions.length}`);
            console.log(`     Permissions: ${rolePermissions.slice(0, 5).join(', ')}${rolePermissions.length > 5 ? '...' : ''}`);
            console.log('');
        }

        // Test permission checks
        console.log('✅ Testing Permission Checks:\n');

        const testCases = [
            { role: 'faculty', permission: 'memo:create', expected: false },
            { role: 'secretary', permission: 'memo:create', expected: true },
            { role: 'admin', permission: 'user:manage:admin', expected: true },
            { role: 'admin', permission: 'user:manage:superadmin', expected: false },
            { role: 'superadmin', permission: 'user:manage:superadmin', expected: true },
            { role: 'superadmin', permission: 'system:manage', expected: true }
        ];

        for (const testCase of testCases) {
            const user = { role: testCase.role };
            const result = await rbacService.can(user, testCase.permission);
            const status = result === testCase.expected ? '✅' : '❌';
            console.log(`   ${status} ${testCase.role} can ${testCase.permission}: ${result} (expected: ${testCase.expected})`);
        }

        console.log('\n✅ RBAC initialization complete!');
        process.exit(0);

    } catch (error) {
        console.error('❌ Error initializing RBAC:', error);
        process.exit(1);
    }
}

// Run the script
initializeRBAC();

