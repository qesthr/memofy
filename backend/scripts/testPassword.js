require('dotenv').config();
const mongoose = require('mongoose');
const User = require('../models/User');
const connectDB = require('../config/db');

async function testPassword() {
    try {
        await connectDB();
        console.log('Connected to MongoDB\n');

        const email = process.argv[2] || '2301107552@student.buksu.edu.ph';
        const testPasswords = process.argv.slice(3) || ['12345678', 'Admin@123'];

        const user = await User.findOne({ email: email.toLowerCase().trim() }).select('+password');

        if (!user) {
            console.log('❌ User not found');
            process.exit(1);
        }

        console.log(`Testing passwords for: ${user.email}\n`);

        for (const testPassword of testPasswords) {
            const isValid = await user.comparePassword(testPassword);
            console.log(`Password "${testPassword}": ${isValid ? '✅ VALID' : '❌ INVALID'}`);
        }

        process.exit(0);
    } catch (error) {
        console.error('Error:', error);
        process.exit(1);
    }
}

testPassword();

