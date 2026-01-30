const mongoose = require('mongoose');
require('dotenv').config();

/**
 * Create all necessary indexes in the new database for better performance
 */

// Get the active database URI
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

    if (process.env.MONGODB_URI_SECONDARY) {
        return process.env.MONGODB_URI_SECONDARY;
    }

    if (process.env.MONGODB_URI_PRIMARY) {
        return process.env.MONGODB_URI_PRIMARY;
    }

    throw new Error('No MongoDB URI found');
}

const mongoURI = getMongoURI();

async function createIndexes() {
    try {
        console.log('🔧 Creating Indexes for Better Performance');
        console.log('═'.repeat(60));
        console.log('Database:', mongoURI.replace(/:[^:@]+@/, ':****@'));
        console.log('═'.repeat(60));

        await mongoose.connect(mongoURI, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const db = mongoose.connection.db;
        console.log('✅ Connected to database\n');

        // Users collection indexes
        console.log('📊 Creating indexes for users collection...');
        const usersCollection = db.collection('users');
        await usersCollection.createIndex({ email: 1 }, { unique: true, background: true });
        await usersCollection.createIndex({ role: 1 }, { background: true });
        await usersCollection.createIndex({ isActive: 1 }, { background: true });
        await usersCollection.createIndex({ department: 1 }, { background: true });
        await usersCollection.createIndex({ googleId: 1 }, { unique: true, sparse: true, background: true });
        await usersCollection.createIndex({ employeeId: 1 }, { unique: true, sparse: true, background: true });
        console.log('   ✅ Users indexes created');

        // Memos collection indexes
        console.log('\n📊 Creating indexes for memos collection...');
        const memosCollection = db.collection('memos');
        await memosCollection.createIndex({ activityType: 1 }, { background: true });
        await memosCollection.createIndex({ recipient: 1, activityType: 1 }, { background: true });
        await memosCollection.createIndex({ sender: 1, folder: 1 }, { background: true });
        await memosCollection.createIndex({ recipient: 1, folder: 1 }, { background: true });
        await memosCollection.createIndex({ status: 1 }, { background: true });
        await memosCollection.createIndex({ createdBy: 1, createdAt: -1 }, { background: true });
        await memosCollection.createIndex({ createdAt: -1 }, { background: true });
        await memosCollection.createIndex({ scheduledSendAt: 1 }, { background: true });
        await memosCollection.createIndex({ sender: 1, status: 1 }, { background: true });
        await memosCollection.createIndex({ recipient: 1, status: 1 }, { background: true });
        console.log('   ✅ Memos indexes created');

        // Calendar Events indexes
        console.log('\n📊 Creating indexes for calendarevents collection...');
        const eventsCollection = db.collection('calendarevents');
        await eventsCollection.createIndex({ startDate: 1 }, { background: true });
        await eventsCollection.createIndex({ endDate: 1 }, { background: true });
        await eventsCollection.createIndex({ createdBy: 1 }, { background: true });
        await eventsCollection.createIndex({ category: 1 }, { background: true });
        await eventsCollection.createIndex({ startDate: 1, endDate: 1 }, { background: true });
        console.log('   ✅ Calendar Events indexes created');

        // Activity Logs indexes
        console.log('\n📊 Creating indexes for activitylogs collection...');
        const activityLogsCollection = db.collection('activitylogs');
        await activityLogsCollection.createIndex({ timestamp: -1 }, { background: true });
        await activityLogsCollection.createIndex({ actorUserId: 1, timestamp: -1 }, { background: true });
        await activityLogsCollection.createIndex({ action: 1 }, { background: true });
        console.log('   ✅ Activity Logs indexes created');

        // Audit Logs indexes
        console.log('\n📊 Creating indexes for auditlogs collection...');
        const auditLogsCollection = db.collection('auditlogs');
        await auditLogsCollection.createIndex({ timestamp: -1 }, { background: true });
        await auditLogsCollection.createIndex({ user: 1, timestamp: -1 }, { background: true });
        await auditLogsCollection.createIndex({ action: 1 }, { background: true });
        console.log('   ✅ Audit Logs indexes created');

        // Signatures indexes
        console.log('\n📊 Creating indexes for signatures collection...');
        const signaturesCollection = db.collection('signatures');
        await signaturesCollection.createIndex({ isActive: 1 }, { background: true });
        await signaturesCollection.createIndex({ createdBy: 1 }, { background: true });
        console.log('   ✅ Signatures indexes created');

        console.log('\n' + '═'.repeat(60));
        console.log('✅ All indexes created successfully!');
        console.log('💡 This should improve query performance significantly.');
        console.log('═'.repeat(60));

        await mongoose.disconnect();

    } catch (error) {
        console.error('\n❌ Error creating indexes:', error.message);
        try {
            await mongoose.disconnect();
        } catch (e) {}
        process.exit(1);
    }
}

createIndexes();

