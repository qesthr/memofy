const mongoose = require('mongoose');
require('dotenv').config();

// Import all models
const Memo = require('../models/Memo');
const User = require('../models/User');
const CalendarEvent = require('../models/CalendarEvent');

async function checkDatabaseHealth() {
    try {
        console.log('🔍 Checking Database Health...\n');

        // Connect to MongoDB with optimized settings
        const startTime = Date.now();
        await mongoose.connect(process.env.MONGODB_URI, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            maxPoolSize: 10,
            minPoolSize: 2,
            maxIdleTimeMS: 30000,
            connectTimeoutMS: 10000
        });
        const connectTime = Date.now() - startTime;

        console.log(`✅ Connected to MongoDB in ${connectTime}ms`);
        console.log(`📊 Database: ${mongoose.connection.db.databaseName}`);
        console.log(`🔌 Connection State: ${mongoose.connection.readyState === 1 ? 'Connected' : 'Disconnected'}\n`);

        // Check connection pool stats
        const poolStats = mongoose.connection.db.serverConfig?.pool;
        if (poolStats) {
            console.log('=== 🔄 Connection Pool Stats ===');
            console.log(`Active Connections: ${poolStats.totalCount || 'N/A'}`);
            console.log(`Available Connections: ${poolStats.availableCount || 'N/A'}`);
            console.log(`Waiting Requests: ${poolStats.waitingCount || 'N/A'}\n`);
        }

        // Get database stats
        const db = mongoose.connection.db;
        const adminDb = db.admin();

        try {
            const dbStats = await db.stats();
            console.log('=== 💾 Database Storage Stats ===');
            console.log(`Total Size: ${(dbStats.dataSize / 1024 / 1024).toFixed(2)} MB`);
            console.log(`Storage Size: ${(dbStats.storageSize / 1024 / 1024).toFixed(2)} MB`);
            console.log(`Index Size: ${(dbStats.indexSize / 1024 / 1024).toFixed(2)} MB`);
            console.log(`Collections: ${dbStats.collections}`);
            console.log(`Objects: ${dbStats.objects.toLocaleString()}\n`);
        } catch (err) {
            console.log('⚠️  Could not fetch database stats (may require admin privileges)\n');
        }

        // Check all collections
        const collections = await db.listCollections().toArray();
        console.log('=== 📁 Collections ===');

        const collectionStats = [];

        for (const col of collections) {
            try {
                const collection = db.collection(col.name);
                const count = await collection.countDocuments();
                const stats = await collection.stats();

                collectionStats.push({
                    name: col.name,
                    count: count,
                    size: stats.size || 0,
                    storageSize: stats.storageSize || 0,
                    avgObjSize: stats.avgObjSize || 0,
                    indexes: stats.nindexes || 0
                });

                console.log(`\n📄 ${col.name}:`);
                console.log(`   Documents: ${count.toLocaleString()}`);
                console.log(`   Size: ${((stats.size || 0) / 1024 / 1024).toFixed(2)} MB`);
                console.log(`   Storage: ${((stats.storageSize || 0) / 1024 / 1024).toFixed(2)} MB`);
                console.log(`   Avg Doc Size: ${((stats.avgObjSize || 0) / 1024).toFixed(2)} KB`);
                console.log(`   Indexes: ${stats.nindexes || 0}`);

                // Check indexes
                const indexes = await collection.indexes();
                if (indexes.length > 0) {
                    console.log(`   Index Details:`);
                    indexes.forEach(idx => {
                        const keys = Object.keys(idx.key).join(', ');
                        console.log(`     - ${keys}${idx.unique ? ' (unique)' : ''}`);
                    });
                }
            } catch (err) {
                console.log(`\n⚠️  ${col.name}: Error getting stats - ${err.message}`);
            }
        }

        // Performance test - query timing
        console.log('\n=== ⚡ Performance Tests ===');

        // Test 1: Count documents
        const countStart = Date.now();
        const userCount = await User.countDocuments();
        const countTime = Date.now() - countStart;
        console.log(`User count query: ${countTime}ms (${userCount} documents)`);

        // Test 2: Find with filter
        const findStart = Date.now();
        const memosCount = await Memo.countDocuments({ status: { $ne: 'deleted' } });
        const findTime = Date.now() - findStart;
        console.log(`Memo count with filter: ${findTime}ms (${memosCount} documents)`);

        // Test 3: Find with populate
        const populateStart = Date.now();
        const recentMemos = await Memo.find({ status: { $ne: 'deleted' } })
            .populate('sender', 'firstName lastName')
            .limit(10)
            .lean();
        const populateTime = Date.now() - populateStart;
        console.log(`Memo find with populate (10 docs): ${populateTime}ms`);

        // Test 4: Aggregation
        const aggStart = Date.now();
        const roleStats = await User.aggregate([
            { $group: { _id: '$role', count: { $sum: 1 } } }
        ]);
        const aggTime = Date.now() - aggStart;
        console.log(`User aggregation by role: ${aggTime}ms`);

        // Check for potential issues
        console.log('\n=== ⚠️  Health Check Results ===');

        const issues = [];
        const warnings = [];

        // Check connection time
        if (connectTime > 5000) {
            issues.push(`Slow connection time: ${connectTime}ms (should be < 5000ms)`);
        } else if (connectTime > 2000) {
            warnings.push(`Connection time is moderate: ${connectTime}ms`);
        }

        // Check query performance
        if (countTime > 1000) {
            issues.push(`Slow count query: ${countTime}ms (should be < 1000ms)`);
        }

        if (findTime > 2000) {
            issues.push(`Slow filtered query: ${findTime}ms (should be < 2000ms)`);
        }

        if (populateTime > 3000) {
            issues.push(`Slow populate query: ${populateTime}ms (should be < 3000ms)`);
        }

        // Check collection sizes
        collectionStats.forEach(col => {
            if (col.count > 100000) {
                warnings.push(`Large collection: ${col.name} has ${col.count.toLocaleString()} documents`);
            }
            if (col.size > 100 * 1024 * 1024) { // 100MB
                warnings.push(`Large collection size: ${col.name} is ${(col.size / 1024 / 1024).toFixed(2)} MB`);
            }
            if (col.indexes < 2 && col.count > 1000) {
                warnings.push(`Collection ${col.name} may need more indexes (${col.indexes} indexes for ${col.count} docs)`);
            }
        });

        // Check for missing indexes on frequently queried fields
        const memoIndexes = await Memo.collection.indexes();
        const memoIndexKeys = memoIndexes.map(idx => Object.keys(idx.key).join('_'));

        const requiredIndexes = [
            'status_activityType',
            'status_activityType_createdAt',
            'sender_status',
            'createdAt'
        ];

        requiredIndexes.forEach(reqIdx => {
            if (!memoIndexKeys.some(key => key.includes(reqIdx.split('_')[0]))) {
                warnings.push(`Missing recommended index on Memo: ${reqIdx}`);
            }
        });

        // Print results
        if (issues.length === 0 && warnings.length === 0) {
            console.log('✅ Database health: GOOD\n');
        } else {
            if (issues.length > 0) {
                console.log('❌ CRITICAL ISSUES:');
                issues.forEach(issue => console.log(`   - ${issue}`));
                console.log('');
            }

            if (warnings.length > 0) {
                console.log('⚠️  WARNINGS:');
                warnings.forEach(warning => console.log(`   - ${warning}`));
                console.log('');
            }
        }

        // Recommendations
        console.log('=== 💡 Recommendations ===');
        if (connectTime > 2000) {
            console.log('1. Check network latency to MongoDB server');
            console.log('2. Consider using MongoDB connection pooling');
        }
        if (collectionStats.some(c => c.count > 50000)) {
            console.log('3. Consider archiving old data for large collections');
        }
        if (collectionStats.some(c => c.indexes < 2 && c.count > 1000)) {
            console.log('4. Add indexes on frequently queried fields');
        }
        try {
            const dbStats = await db.stats();
            if (dbStats && dbStats.storageSize > 500 * 1024 * 1024) { // 500MB
                console.log('5. Consider database cleanup/archiving for large storage');
            }
        } catch (err) {
            // Stats not available, skip
        }

        console.log('\n✅ Health check complete!');

    } catch (error) {
        console.error('❌ Error checking database health:', error);
        process.exit(1);
    } finally {
        await mongoose.disconnect();
        console.log('\n🔌 Disconnected from MongoDB');
    }
}

// Run the health check
checkDatabaseHealth();

