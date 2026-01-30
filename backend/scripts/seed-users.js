/* eslint-disable no-console */
/**
 * Database Seeding Script for User Accounts
 * Creates test accounts for Admin, Secretary, and Faculty roles
 * 
 * Usage: npm run seed:users
 */

require('dotenv').config();
const mongoose = require('mongoose');
const User = require('../models/User');

// Test user accounts to be created
const testUsers = [
    {
        email: 'admin@buksu.edu.ph',
        password: 'Admin123!',
        role: 'admin',
        firstName: 'System',
        lastName: 'Administrator',
        employeeId: 'ADMIN-001',
        department: '', // Admins don't have departments
        status: 'active',
        isActive: true,
        canCrossSend: true,
        canAddSignature: true
    },
    {
        email: 'secretary@buksu.edu.ph',
        password: 'Secretary123!',
        role: 'secretary',
        firstName: 'Department',
        lastName: 'Secretary',
        employeeId: 'SEC-001',
        department: 'Computer Science',
        status: 'active',
        isActive: true,
        canCrossSend: false,
        canAddSignature: true
    },
    {
        email: 'faculty@buksu.edu.ph',
        password: 'Faculty123!',
        role: 'faculty',
        firstName: 'John',
        lastName: 'Doe',
        employeeId: 'FAC-001',
        department: 'Computer Science',
        status: 'active',
        isActive: true,
        canCrossSend: false,
        canAddSignature: false
    }
];

/**
 * Get the active MongoDB URI (same logic as db.js)
 */
function getMongoURI() {
    const activeDB = process.env.MONGODB_ACTIVE;

    if (activeDB === 'secondary' && process.env.MONGODB_URI_SECONDARY) {
        return process.env.MONGODB_URI_SECONDARY;
    }

    if (activeDB === 'primary' && process.env.MONGODB_URI_PRIMARY) {
        return process.env.MONGODB_URI_PRIMARY;
    }

    if (process.env.MONGODB_URI) {
        return process.env.MONGODB_URI;
    }

    if (process.env.MONGODB_URI_PRIMARY) {
        return process.env.MONGODB_URI_PRIMARY;
    }

    if (process.env.MONGODB_URI_SECONDARY) {
        return process.env.MONGODB_URI_SECONDARY;
    }

    throw new Error('No MongoDB URI found. Please set MONGODB_URI, MONGODB_URI_PRIMARY, or MONGODB_URI_SECONDARY');
}

/**
 * Seed the database with test users
 */
async function seedUsers() {
    try {
        console.log('🌱 Starting user seeding process...\n');

        // Connect to MongoDB
        const mongoURI = getMongoURI();
        const maskedURI = mongoURI.replace(/:[^:@]+@/, ':****@');
        console.log(`🔗 Connecting to: ${maskedURI}`);

        await mongoose.connect(mongoURI, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000,
        });

        console.log('✅ Connected to MongoDB\n');

        // Check if users already exist
        const existingUsers = await User.find({
            email: { $in: testUsers.map(u => u.email) }
        });

        if (existingUsers.length > 0) {
            console.log('⚠️  Warning: Some test users already exist:');
            existingUsers.forEach(user => {
                console.log(`   - ${user.email} (${user.role})`);
            });
            console.log('\n❓ Do you want to delete existing users and recreate them?');
            console.log('   To proceed, delete the users manually or drop the collection.\n');
            
            // Show existing users but don't delete them automatically
            console.log('📊 Existing users in database:');
            const allUsers = await User.find({}).select('email role firstName lastName department status');
            allUsers.forEach(user => {
                console.log(`   - ${user.email} | ${user.role} | ${user.firstName} ${user.lastName} | ${user.department || 'N/A'} | ${user.status}`);
            });
            
            await mongoose.connection.close();
            console.log('\n❌ Seeding cancelled to prevent duplicates.');
            console.log('💡 Tip: To recreate users, first delete them from MongoDB Atlas or run:');
            console.log('   User.deleteMany({ email: { $in: ["admin@buksu.edu.ph", "secretary@buksu.edu.ph", "faculty@buksu.edu.ph"] } })');
            process.exit(0);
        }

        // Create users
        console.log('👥 Creating test user accounts...\n');
        const createdUsers = [];

        for (const userData of testUsers) {
            try {
                const user = new User(userData);
                await user.save();
                createdUsers.push(user);
                
                console.log(`✅ Created ${userData.role.toUpperCase()} account:`);
                console.log(`   Email: ${userData.email}`);
                console.log(`   Password: ${userData.password}`);
                console.log(`   Name: ${userData.firstName} ${userData.lastName}`);
                console.log(`   Employee ID: ${userData.employeeId}`);
                console.log(`   Department: ${userData.department || 'N/A (Admin)'}`);
                console.log(`   Status: ${userData.status}`);
                console.log('');
            } catch (error) {
                console.error(`❌ Failed to create ${userData.role} account:`, error.message);
            }
        }

        console.log(`\n🎉 Successfully created ${createdUsers.length} user account(s)!\n`);

        // Display summary
        console.log('📋 Login Credentials Summary:');
        console.log('═══════════════════════════════════════════════════════════');
        testUsers.forEach(user => {
            console.log(`${user.role.toUpperCase().padEnd(12)} | ${user.email.padEnd(30)} | ${user.password}`);
        });
        console.log('═══════════════════════════════════════════════════════════\n');

        console.log('💡 Next Steps:');
        console.log('   1. Start the application: npm start');
        console.log('   2. Navigate to: http://localhost:5000');
        console.log('   3. Login with any of the accounts above');
        console.log('   4. Verify role-based permissions are working\n');

        // Close connection
        await mongoose.connection.close();
        console.log('✅ Database connection closed');
        process.exit(0);

    } catch (error) {
        console.error('\n❌ Error during seeding process:', error.message);
        
        if (error.message.includes('ENOTFOUND') || error.message.includes('querySrv')) {
            console.error('\n💡 Connection Error Tips:');
            console.error('   1. Check your MongoDB connection string in .env');
            console.error('   2. Ensure your IP address is whitelisted in MongoDB Atlas');
            console.error('   3. Verify your database credentials are correct');
            console.error('   4. Check your internet connection\n');
        }
        
        if (mongoose.connection.readyState === 1) {
            await mongoose.connection.close();
        }
        process.exit(1);
    }
}

// Run the seeding function
seedUsers();
