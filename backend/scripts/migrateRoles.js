/**
 * Migration Script: Remove superadmin role and add roleVersion fields
 *
 * This script should be run once after deploying the RBAC changes.
 * It will:
 * 1. Convert all superadmin users to admin
 * 2. Add roleVersion and roleUpdatedAt fields to all users
 *
 * Usage: node backend/scripts/migrateRoles.js
 */

require('dotenv').config();
const mongoose = require('mongoose');
const connectDB = require('../config/db');
const User = require('../models/User');

async function migrateRoles() {
    try {
        // Connect to database
        await connectDB();
        console.log('✅ Connected to database');

        // Step 1: Update superadmin users to admin
        const superadminUpdate = await User.updateMany(
            { role: 'superadmin' },
            {
                $set: {
                    role: 'admin',
                    roleVersion: 1,
                    roleUpdatedAt: new Date()
                }
            }
        );
        console.log(`✅ Updated ${superadminUpdate.modifiedCount} superadmin users to admin`);

        // Step 2: Add roleVersion to users without it
        const roleVersionUpdate = await User.updateMany(
            { roleVersion: { $exists: false } },
            {
                $set: {
                    roleVersion: 1,
                    roleUpdatedAt: new Date()
                }
            }
        );
        console.log(`✅ Added roleVersion to ${roleVersionUpdate.modifiedCount} users`);

        // Step 3: Add roleUpdatedAt to users without it
        const roleUpdatedAtUpdate = await User.updateMany(
            { roleUpdatedAt: { $exists: false } },
            {
                $set: {
                    roleUpdatedAt: new Date()
                }
            }
        );
        console.log(`✅ Added roleUpdatedAt to ${roleUpdatedAtUpdate.modifiedCount} users`);

        // Step 4: Verify migration
        const superadminCount = await User.countDocuments({ role: 'superadmin' });
        const usersWithoutRoleVersion = await User.countDocuments({ roleVersion: { $exists: false } });
        const usersWithoutRoleUpdatedAt = await User.countDocuments({ roleUpdatedAt: { $exists: false } });

        console.log('\n📊 Migration Summary:');
        console.log(`   - Remaining superadmin users: ${superadminCount}`);
        console.log(`   - Users without roleVersion: ${usersWithoutRoleVersion}`);
        console.log(`   - Users without roleUpdatedAt: ${usersWithoutRoleUpdatedAt}`);

        if (superadminCount === 0 && usersWithoutRoleVersion === 0 && usersWithoutRoleUpdatedAt === 0) {
            console.log('\n✅ Migration completed successfully!');
        } else {
            console.log('\n⚠️  Some users still need migration. Please review.');
        }

        // Close database connection
        await mongoose.connection.close();
        console.log('✅ Database connection closed');
        process.exit(0);

    } catch (error) {
        console.error('❌ Migration failed:', error);
        process.exit(1);
    }
}

// Run migration
migrateRoles();

