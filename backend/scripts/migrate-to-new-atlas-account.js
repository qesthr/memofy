const mongoose = require('mongoose');
require('dotenv').config();

/**
 * MongoDB Atlas Account Migration Verification Script
 * Compares data between old and new Atlas accounts
 */

const OLD_ATLAS_URI = process.env.MONGODB_URI_OLD || process.env.MONGODB_URI;
const NEW_ATLAS_URI = process.env.MONGODB_URI_NEW;

async function checkConnection(uri, label) {
    try {
        console.log(`\n🔌 Testing ${label} connection...`);
        await mongoose.connect(uri, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const dbName = mongoose.connection.db.databaseName;
        const host = mongoose.connection.host;
        const collections = await mongoose.connection.db.listCollections().toArray();

        console.log(`✅ ${label} connected successfully!`);
        console.log(`   Host: ${host}`);
        console.log(`   Database: ${dbName}`);
        console.log(`   Collections: ${collections.length}`);

        await mongoose.disconnect();
        return { success: true, collections: collections.length, host, dbName };
    } catch (error) {
        console.error(`❌ ${label} connection failed: ${error.message}`);
        if (error.name === 'MongoServerSelectionError') {
            console.error('   💡 Check: IP whitelist, network access, cluster status');
        } else if (error.name === 'MongoAuthenticationError') {
            console.error('   💡 Check: Username, password, database user permissions');
        }
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
            try {
                const collection = db.collection(col.name);
                const count = await collection.countDocuments();
                const statsInfo = await db.command({ collStats: col.name }).catch(() => ({}));

                stats.push({
                    name: col.name,
                    count: count,
                    size: statsInfo.size || 0,
                    storageSize: statsInfo.storageSize || 0,
                    indexes: statsInfo.nindexes || 0
                });
            } catch (err) {
                console.warn(`   ⚠️  Could not get stats for ${col.name}: ${err.message}`);
                stats.push({
                    name: col.name,
                    count: 0,
                    size: 0,
                    storageSize: 0,
                    indexes: 0
                });
            }
        }

        await mongoose.disconnect();
        return stats;
    } catch (error) {
        console.error(`Error getting stats: ${error.message}`);
        return [];
    }
}

async function compareCollections(oldStats, newStats) {
    console.log('\n📋 Collection Comparison:');
    console.log('═'.repeat(80));
    console.log(
        'Collection Name'.padEnd(25) +
        'Old Account'.padEnd(20) +
        'New Account'.padEnd(20) +
        'Status'
    );
    console.log('─'.repeat(80));

    const allCollections = new Set([...oldStats.map(s => s.name), ...newStats.map(s => s.name)]);
    let allMatch = true;
    let totalOldDocs = 0;
    let totalNewDocs = 0;

    for (const colName of Array.from(allCollections).sort()) {
        const oldCol = oldStats.find(c => c.name === colName);
        const newCol = newStats.find(c => c.name === colName);

        if (oldCol && newCol) {
            const countMatch = oldCol.count === newCol.count;
            const status = countMatch ? '✅ Match' : '⚠️  Count Mismatch';
            if (!countMatch) {allMatch = false;}

            totalOldDocs += oldCol.count;
            totalNewDocs += newCol.count;

            console.log(
                colName.padEnd(25) +
                oldCol.count.toString().padEnd(20) +
                newCol.count.toString().padEnd(20) +
                status
            );
        } else if (oldCol && !newCol) {
            console.log(
                colName.padEnd(25) +
                oldCol.count.toString().padEnd(20) +
                'Missing'.padEnd(20) +
                '❌ Not in New'
            );
            allMatch = false;
            totalOldDocs += oldCol.count;
        } else if (!oldCol && newCol) {
            console.log(
                colName.padEnd(25) +
                'Missing'.padEnd(20) +
                newCol.count.toString().padEnd(20) +
                '⚠️  Extra in New'
            );
            totalNewDocs += newCol.count;
        }
    }

    console.log('─'.repeat(80));
    console.log(
        'TOTAL'.padEnd(25) +
        totalOldDocs.toString().padEnd(20) +
        totalNewDocs.toString().padEnd(20) +
        (totalOldDocs === totalNewDocs ? '✅ Match' : '⚠️  Mismatch')
    );
    console.log('═'.repeat(80));

    return allMatch && totalOldDocs === totalNewDocs;
}

async function verifyMigration() {
    if (!NEW_ATLAS_URI) {
        console.error('\n❌ MONGODB_URI_NEW not set in .env file!');
        console.error('\n📝 Please add to your .env file:');
        console.error('   MONGODB_URI_OLD=mongodb+srv://... (old account)');
        console.error('   MONGODB_URI_NEW=mongodb+srv://... (new account)');
        console.error('\n   Or set MONGODB_URI_NEW with your new Atlas connection string.');
        process.exit(1);
    }

    console.log('🔍 MongoDB Atlas Account Migration Verification');
    console.log('═'.repeat(80));

    // Check old account
    console.log('\n📊 Step 1: Checking Old Atlas Account...');
    const oldCheck = await checkConnection(OLD_ATLAS_URI, 'Old Atlas Account');
    if (!oldCheck.success) {
        console.error('\n❌ Cannot connect to old account. Aborting.');
        process.exit(1);
    }

    // Check new account
    console.log('\n📊 Step 2: Checking New Atlas Account...');
    const newCheck = await checkConnection(NEW_ATLAS_URI, 'New Atlas Account');
    if (!newCheck.success) {
        console.error('\n❌ Cannot connect to new account. Please check:');
        console.error('   1. Cluster is created and running');
        console.error('   2. IP whitelist includes your IP');
        console.error('   3. Database user credentials are correct');
        console.error('   4. Connection string is correct');
        process.exit(1);
    }

    // Get detailed stats
    console.log('\n📊 Step 3: Comparing Data...');
    const oldData = await getCollectionStats(OLD_ATLAS_URI);
    const newData = await getCollectionStats(NEW_ATLAS_URI);

    // Compare
    const match = await compareCollections(oldData, newData);

    // Show total sizes
    const oldTotal = oldData.reduce((sum, col) => sum + col.size, 0);
    const newTotal = newData.reduce((sum, col) => sum + col.size, 0);

    console.log('\n💾 Data Size Comparison:');
    console.log(`   Old Account: ${(oldTotal / 1024 / 1024).toFixed(2)} MB`);
    console.log(`   New Account: ${(newTotal / 1024 / 1024).toFixed(2)} MB`);
    console.log(`   Difference: ${((newTotal - oldTotal) / 1024 / 1024).toFixed(2)} MB`);

    // Final verdict
    console.log('\n' + '═'.repeat(80));
    if (match) {
        console.log('✅ Migration verification SUCCESSFUL!');
        console.log('   All collections match. Ready to switch production.');
        console.log('\n📝 Next Steps:');
        console.log('   1. Update .env: Change MONGODB_URI to new connection string');
        console.log('   2. Restart your application');
        console.log('   3. Monitor for any issues');
    } else {
        console.log('⚠️  Migration verification found DIFFERENCES.');
        console.log('   Please review the data before switching production.');
        console.log('\n💡 Recommendations:');
        console.log('   1. Check if all collections were restored');
        console.log('   2. Verify document counts match');
        console.log('   3. Re-run migration if needed');
    }
    console.log('═'.repeat(80));
}

// Main execution
if (require.main === module) {
    verifyMigration()
        .then(() => {
            process.exit(0);
        })
        .catch((error) => {
            console.error('\n❌ Verification failed:', error);
            process.exit(1);
        });
}

module.exports = { verifyMigration, checkConnection, getCollectionStats, compareCollections };

