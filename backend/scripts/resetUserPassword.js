require('dotenv').config();
const mongoose = require('mongoose');
const User = require('../models/User');
const connectDB = require('../config/db');

async function resetPassword() {
    try {
        await connectDB();
        console.log('Connected to MongoDB\n');

        const email = process.argv[2];
        const newPassword = process.argv[3];

        if (!email || !newPassword) {
            console.log('Usage: node resetUserPassword.js <email> <newPassword>');
            console.log('Example: node resetUserPassword.js 2301107552@student.buksu.edu.ph Admin@123');
            process.exit(1);
        }

        const user = await User.findOne({ email: email.toLowerCase().trim() });

        if (!user) {
            console.log('❌ User not found');
            process.exit(1);
        }

        console.log(`Resetting password for: ${user.email}`);
        console.log(`Name: ${user.firstName} ${user.lastName}`);
        console.log(`Role: ${user.role}\n`);

        // Set password directly - the pre-save hook will hash it
        user.password = newPassword;
        await user.save();

        console.log('✅ Password reset successfully!');
        console.log(`New password: ${newPassword}`);
        console.log('\n⚠️  Please test login with the new password.');

        // Verify it works
        const testUser = await User.findOne({ email: email.toLowerCase().trim() }).select('+password');
        const isValid = await testUser.comparePassword(newPassword);
        console.log(`\nPassword verification: ${isValid ? '✅ WORKS' : '❌ FAILED'}`);

        process.exit(0);
    } catch (error) {
        console.error('Error:', error);
        process.exit(1);
    }
}

resetPassword();

