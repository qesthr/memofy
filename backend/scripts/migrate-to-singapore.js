const mongoose = require('mongoose');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
require('dotenv').config();

/**
 * MongoDB Region Migration Script
 * Migrates data from Hong Kong cluster to Singapore cluster
 */

const OLD_CLUSTER_URI = process.env.MONGODB_URI; // Current Hong Kong cluster
const NEW_CLUSTER_URI = process.env.MONGODB_URI_SINGAPORE; // New Singapore cluster (set in .env)

async function checkConnection(uri, label) {
    try {
        console.log(`\n🔌 Testing ${label} connection...`);
        await mongoose.connect(uri, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const dbName = mongoose.connection.db.databaseName;
        const collections = await mongoose.connection.db.listCollections().toArray();

        console.log(`✅ ${label} connected successfully!`);
        console.log(`   Database: ${dbName}`);
        console.log(`   Collections: ${collections.length}`);

        await mongoose.disconnect();
        return { success: true, collections: collections.length };
    } catch (error) {
        console.error(`❌ ${label} connection failed: ${error.message}`);
        return { success: false, error: error.message };
    }
}

async function getCollectionStats(uri) {
    try {
        await mongoose.connect(uri, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const db = mongoose.connection.db;
        const collections = await db.listCollections().toArray();
        const stats = [];

        for (const col of collections) {
            const collection = db.collection(col.name);
            const count = await collection.countDocuments();
            const statsInfo = await db.command({ collStats: col.name });

            stats.push({
                name: col.name,
                count: count,
                size: statsInfo.size || 0,
                storageSize: statsInfo.storageSize || 0
            });
        }

        await mongoose.disconnect();
        return stats;
    } catch (error) {
        console.error(`Error getting stats: ${error.message}`);
        return [];
    }
}

async function verifyMigration() {
    if (!NEW_CLUSTER_URI) {
        console.error('\n❌ MONGODB_URI_SINGAPORE not set in .env file!');
        console.error('   Please add: MONGODB_URI_SINGAPORE=mongodb+srv://...');
        process.exit(1);
    }

    console.log('🔍 MongoDB Region Migration Verification\n');
    console.log('='.repeat(60));

    // Check old cluster
    const oldStats = await checkConnection(OLD_CLUSTER_URI, 'Old Cluster (Hong Kong)');
    if (!oldStats.success) {
        console.error('\n❌ Cannot connect to old cluster. Aborting.');
        process.exit(1);
    }

    // Check new cluster
    const newStats = await checkConnection(NEW_CLUSTER_URI, 'New Cluster (Singapore)');
    if (!newStats.success) {
        console.error('\n❌ Cannot connect to new cluster. Please check:');
        console.error('   1. Cluster is created and running');
        console.error('   2. IP whitelist includes your IP');
        console.error('   3. Database user credentials are correct');
        console.error('   4. Connection string is correct');
        process.exit(1);
    }

    // Get detailed stats
    console.log('\n📊 Comparing data...');
    const oldData = await getCollectionStats(OLD_CLUSTER_URI);
    const newData = await getCollectionStats(NEW_CLUSTER_URI);

    console.log('\n📋 Collection Comparison:');
    console.log('─'.repeat(60));
    console.log('Collection Name'.padEnd(25) + 'Old (HK)'.padEnd(15) + 'New (SG)'.padEnd(15) + 'Status');
    console.log('─'.repeat(60));

    let allMatch = true;
    for (const oldCol of oldData) {
        const newCol = newData.find(c => c.name === oldCol.name);
        if (newCol) {
            const match = oldCol.count === newCol.count;
            const status = match ? '✅ Match' : '⚠️  Mismatch';
            if (!match) {allMatch = false;}

            console.log(
                oldCol.name.padEnd(25) +
                oldCol.count.toString().padEnd(15) +
                newCol.count.toString().padEnd(15) +
                status
            );
        } else {
            console.log(
                oldCol.name.padEnd(25) +
                oldCol.count.toString().padEnd(15) +
                'Missing'.padEnd(15) +
                '❌ Not Found'
            );
            allMatch = false;
        }
    }

    console.log('─'.repeat(60));

    if (allMatch) {
        console.log('\n✅ Migration verification successful!');
        console.log('   All collections match. Ready to switch production.');
    } else {
        console.log('\n⚠️  Migration verification found differences.');
        console.log('   Please review the data before switching production.');
    }

    // Show total sizes
    const oldTotal = oldData.reduce((sum, col) => sum + col.size, 0);
    const newTotal = newData.reduce((sum, col) => sum + col.size, 0);

    console.log('\n💾 Total Data Size:');
    console.log(`   Old Cluster: ${(oldTotal / 1024 / 1024).toFixed(2)} MB`);
    console.log(`   New Cluster: ${(newTotal / 1024 / 1024).toFixed(2)} MB`);
}

// Main execution
if (require.main === module) {
    verifyMigration()
        .then(() => {
            console.log('\n✅ Verification complete!');
            process.exit(0);
        })
        .catch((error) => {
            console.error('\n❌ Verification failed:', error);
            process.exit(1);
        });
}

module.exports = { verifyMigration, checkConnection, getCollectionStats };

