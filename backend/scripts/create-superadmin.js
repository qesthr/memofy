/**
 * Create Superadmin Script
 *
 * This script creates the first superadmin user in the system.
 * Run with: node backend/scripts/create-superadmin.js
 *
 * Usage:
 *   node backend/scripts/create-superadmin.js <email> <password> [firstName] [lastName]
 *
 * Example:
 *   node backend/scripts/create-superadmin.js admin@buksu.edu.ph SecurePassword123 Admin User
 */

require('dotenv').config();
const mongoose = require('mongoose');
const User = require('../models/User');
const bcrypt = require('bcryptjs');

async function createSuperAdmin() {
    try {
        // Connect to MongoDB
        const mongoUri = process.env.MONGODB_URI || 'mongodb://localhost:27017/memofy';
        await mongoose.connect(mongoUri);
        console.log('✅ Connected to MongoDB');

        // Get arguments from command line
        const args = process.argv.slice(2);

        if (args.length < 2) {
            console.error('❌ Error: Missing required arguments');
            console.log('\nUsage:');
            console.log('  node backend/scripts/create-superadmin.js <email> <password> [firstName] [lastName]');
            console.log('\nExample:');
            console.log('  node backend/scripts/create-superadmin.js admin@buksu.edu.ph SecurePassword123 Admin User');
            process.exit(1);
        }

        const email = args[0];
        const password = args[1];
        const firstName = args[2] || 'Super';
        const lastName = args[3] || 'Admin';

        // Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            console.error('❌ Error: Invalid email format');
            process.exit(1);
        }

        // Validate password
        if (password.length < 8) {
            console.error('❌ Error: Password must be at least 8 characters long');
            process.exit(1);
        }

        // Check if superadmin already exists
        const existingSuperAdmin = await User.findOne({ role: 'superadmin' });
        if (existingSuperAdmin) {
            console.log('⚠️  Warning: A superadmin already exists in the system');
            console.log(`   Email: ${existingSuperAdmin.email}`);
            console.log('\nDo you want to create another superadmin? (y/n)');

            // For non-interactive mode, exit
            if (process.env.NODE_ENV === 'production') {
                console.log('❌ Exiting: Superadmin already exists');
                process.exit(1);
            }
        }

        // Check if user with this email already exists
        const existingUser = await User.findOne({ email: email.toLowerCase() });
        if (existingUser) {
            if (existingUser.role === 'superadmin') {
                console.log('✅ User is already a superadmin');
                process.exit(0);
            }

            // Update existing user to superadmin
            console.log(`⚠️  User with email ${email} already exists. Updating to superadmin...`);
            existingUser.role = 'superadmin';
            existingUser.firstName = firstName;
            existingUser.lastName = lastName;

            // Hash password if provided
            if (password) {
                const salt = await bcrypt.genSalt(10);
                existingUser.password = await bcrypt.hash(password, salt);
            }

            await existingUser.save();
            console.log('✅ User updated to superadmin successfully!');
            console.log(`   Email: ${existingUser.email}`);
            console.log(`   Name: ${existingUser.firstName} ${existingUser.lastName}`);
            console.log(`   Role: ${existingUser.role}`);

            await mongoose.disconnect();
            process.exit(0);
        }

        // Hash password
        const salt = await bcrypt.genSalt(10);
        const hashedPassword = await bcrypt.hash(password, salt);

        // Create superadmin user
        const superAdmin = new User({
            email: email.toLowerCase(),
            password: hashedPassword,
            firstName: firstName,
            lastName: lastName,
            role: 'superadmin',
            isActive: true,
            status: 'active',
            emailVerified: true
        });

        await superAdmin.save();

        console.log('\n✅ Superadmin created successfully!');
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        console.log(`   Email: ${superAdmin.email}`);
        console.log(`   Name: ${superAdmin.firstName} ${superAdmin.lastName}`);
        console.log(`   Role: ${superAdmin.role}`);
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        console.log('\n⚠️  IMPORTANT: Keep these credentials secure!');
        console.log('   You can now login with these credentials.');

        await mongoose.disconnect();
        console.log('\n✅ Disconnected from MongoDB');
        process.exit(0);

    } catch (error) {
        console.error('❌ Error creating superadmin:', error);
        await mongoose.disconnect();
        process.exit(1);
    }
}

// Run the script
createSuperAdmin();

