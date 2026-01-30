require('dotenv').config();
const mongoose = require('mongoose');
const User = require('../models/User');
const connectDB = require('../config/db');

async function checkUserPasswords() {
    try {
        await connectDB();
        console.log('Connected to MongoDB\n');

        const emails = [
            '2301107552@student.buksu.edu.ph',
            'admin@buksu.edu.ph'
        ];

        for (const email of emails) {
            console.log(`\n=== Checking: ${email} ===`);
            const user = await User.findOne({ email: email.toLowerCase().trim() }).select('+password');

            if (!user) {
                console.log('❌ User not found');
                continue;
            }

            console.log(`✅ User found:`);
            console.log(`   - ID: ${user._id}`);
            console.log(`   - Name: ${user.firstName} ${user.lastName}`);
            console.log(`   - Role: ${user.role}`);
            console.log(`   - Active: ${user.isActive}`);
            console.log(`   - Has Password: ${!!user.password}`);

            if (user.password) {
                console.log(`   - Password Hash: ${user.password.substring(0, 20)}... (truncated)`);
                console.log(`   - Hash Length: ${user.password.length} characters`);
                console.log(`   - Hash Format: ${user.password.startsWith('$2') ? 'bcrypt (valid)' : 'unknown format'}`);
            } else {
                console.log('   ⚠️  No password set - user must use Google login');
            }

            console.log(`   - Last Login: ${user.lastLogin || 'Never'}`);
            console.log(`   - Created: ${user.createdAt}`);
            console.log(`   - Updated: ${user.updatedAt}`);
        }

        process.exit(0);
    } catch (error) {
        console.error('Error:', error);
        process.exit(1);
    }
}

checkUserPasswords();

