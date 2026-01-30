const mongoose = require('mongoose');
require('dotenv').config();

/**
 * MongoDB Collection Migration Script
 * Migrates all collections from old database to new database
 * Special handling for users collection to ensure data integrity
 */

// Get URIs - try multiple environment variable names for flexibility
// OLD: Original database (Hong Kong)
const OLD_URI = process.env.MONGODB_URI_PRIMARY || process.env.MONGODB_URI_OLD ||
    (process.env.MONGODB_URI && process.env.MONGODB_URI.includes('ailayze') ? process.env.MONGODB_URI : null);
// NEW: New database (Singapore or new account)
const NEW_URI = process.env.MONGODB_URI_SECONDARY || process.env.MONGODB_URI_NEW || process.env.MONGODB_URI_SINGAPORE ||
    (process.env.MONGODB_URI && !process.env.MONGODB_URI.includes('ailayze') ? process.env.MONGODB_URI : null) ||
    // Also check for the new format
    (process.env.MONGODB_URI && process.env.MONGODB_URI.includes('y3m50tg') ? process.env.MONGODB_URI : null);

if (!OLD_URI || !NEW_URI) {
    console.error('\n❌ Missing MongoDB connection strings in .env file');
    console.error('═'.repeat(60));
    console.error('Required: Two MongoDB URIs (old and new)');
    console.error('\nOption 1 (Recommended):');
    console.error('   MONGODB_URI_PRIMARY=mongodb+srv://... (old database)');
    console.error('   MONGODB_URI_SECONDARY=mongodb+srv://... (new database)');
    console.error('\nOption 2:');
    console.error('   MONGODB_URI_OLD=mongodb+srv://... (old database)');
    console.error('   MONGODB_URI_NEW=mongodb+srv://... (new database)');
    console.error('\nOption 3:');
    console.error('   MONGODB_URI=mongodb+srv://... (old database)');
    console.error('   MONGODB_URI_SINGAPORE=mongodb+srv://... (new database)');
    console.error('═'.repeat(60));
    console.error('\nCurrent status:');
    console.error('   OLD_URI:', OLD_URI ? `✅ Set (${OLD_URI.substring(0, 30)}...)` : '❌ Missing');
    console.error('   NEW_URI:', NEW_URI ? `✅ Set (${NEW_URI.substring(0, 30)}...)` : '❌ Missing');
    console.error('\n💡 Add the missing URI to your .env file and try again.\n');
    process.exit(1);
}

async function getCollections(uri) {
    try {
        await mongoose.connect(uri, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const db = mongoose.connection.db;
        const collections = await db.listCollections().toArray();

        await mongoose.disconnect();
        return collections.map(c => c.name);
    } catch (error) {
        console.error(`Error getting collections: ${error.message}`);
        throw error;
    }
}

async function migrateCollection(oldURI, newURI, collectionName, options = {}) {
    const { drop = false, preserveIds = true } = options;

    console.log(`\n📦 Migrating collection: ${collectionName}`);
    console.log('─'.repeat(60));

    try {
        // Connect to old database
        await mongoose.connect(oldURI, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const oldDb = mongoose.connection.db;
        const oldCollection = oldDb.collection(collectionName);

        // Get count
        const count = await oldCollection.countDocuments();
        console.log(`   📊 Documents in old DB: ${count}`);

        if (count === 0) {
            console.log(`   ⚠️  Collection is empty, skipping...`);
            await mongoose.disconnect();
            return { success: true, count: 0, skipped: true };
        }

        // Fetch all documents
        console.log(`   📥 Fetching documents...`);
        const documents = await oldCollection.find({}).toArray();

        await mongoose.disconnect();

        // Connect to new database
        await mongoose.connect(newURI, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const newDb = mongoose.connection.db;
        const newCollection = newDb.collection(collectionName);

        // Drop collection if requested
        if (drop) {
            console.log(`   🗑️  Dropping existing collection...`);
            await newCollection.drop().catch(() => {
                console.log(`   ℹ️  Collection doesn't exist, creating new one...`);
            });
        }

        // Check if collection already has data
        const existingCount = await newCollection.countDocuments();
        if (existingCount > 0 && !drop) {
            console.log(`   ⚠️  Collection already has ${existingCount} documents`);
            console.log(`   💡 Use --drop flag to replace, or collection will be skipped`);
            await mongoose.disconnect();
            return { success: false, count: existingCount, skipped: true, reason: 'Collection already exists' };
        }

        // Insert documents
        console.log(`   📤 Inserting ${documents.length} documents...`);

        if (documents.length > 0) {
            // For large collections, insert in batches
            const batchSize = 1000;
            let inserted = 0;

            for (let i = 0; i < documents.length; i += batchSize) {
                const batch = documents.slice(i, i + batchSize);
                await newCollection.insertMany(batch, { ordered: false });
                inserted += batch.length;
                console.log(`   ⏳ Progress: ${inserted}/${documents.length} (${Math.round(inserted/documents.length*100)}%)`);
            }
        }

        // Verify
        const newCount = await newCollection.countDocuments();
        console.log(`   ✅ Documents in new DB: ${newCount}`);

        if (newCount !== count) {
            console.log(`   ⚠️  Count mismatch! Expected ${count}, got ${newCount}`);
        }

        await mongoose.disconnect();

        return { success: true, count: newCount, expected: count };

    } catch (error) {
        console.error(`   ❌ Error migrating ${collectionName}: ${error.message}`);
        try {
            await mongoose.disconnect();
        } catch (e) {
            // Ignore disconnect errors
        }
        return { success: false, error: error.message };
    }
}

async function migrateUsersCollection(oldURI, newURI, options = {}) {
    const { drop = false } = options;

    console.log(`\n👥 Migrating USERS collection (with special handling)...`);
    console.log('═'.repeat(60));

    try {
        // Connect to old database
        await mongoose.connect(oldURI, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const oldDb = mongoose.connection.db;
        const oldUsers = oldDb.collection('users');

        const count = await oldUsers.countDocuments();
        console.log(`📊 Total users in old DB: ${count}`);

        if (count === 0) {
            console.log(`⚠️  No users found, skipping...`);
            await mongoose.disconnect();
            return { success: true, count: 0 };
        }

        // Fetch all users
        console.log(`📥 Fetching users...`);
        const users = await oldUsers.find({}).toArray();

        // Show user breakdown
        const activeUsers = users.filter(u => u.isActive !== false).length;
        const admins = users.filter(u => u.role === 'admin').length;
        const secretaries = users.filter(u => u.role === 'secretary').length;
        const faculty = users.filter(u => u.role === 'faculty').length;

        console.log(`   Active: ${activeUsers}`);
        console.log(`   Admins: ${admins}`);
        console.log(`   Secretaries: ${secretaries}`);
        console.log(`   Faculty: ${faculty}`);

        await mongoose.disconnect();

        // Connect to new database
        await mongoose.connect(newURI, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const newDb = mongoose.connection.db;
        const newUsers = newDb.collection('users');

        // Drop if requested
        if (drop) {
            console.log(`🗑️  Dropping existing users collection...`);
            await newUsers.drop().catch(() => {
                console.log(`ℹ️  Collection doesn't exist, creating new one...`);
            });
        } else {
            const existingCount = await newUsers.countDocuments();
            if (existingCount > 0) {
                console.log(`⚠️  Users collection already has ${existingCount} users`);
                console.log(`💡 Use --drop flag to replace existing users`);
                await mongoose.disconnect();
                return { success: false, skipped: true, reason: 'Users already exist' };
            }
        }

        // Insert users
        console.log(`📤 Inserting ${users.length} users...`);

        if (users.length > 0) {
            // Insert in batches for better performance
            const batchSize = 500;
            let inserted = 0;

            for (let i = 0; i < users.length; i += batchSize) {
                const batch = users.slice(i, i + batchSize);
                await newUsers.insertMany(batch, { ordered: false });
                inserted += batch.length;
                console.log(`   ⏳ Progress: ${inserted}/${users.length} (${Math.round(inserted/users.length*100)}%)`);
            }
        }

        // Verify
        const newCount = await newUsers.countDocuments();
        const newActiveUsers = await newUsers.countDocuments({ isActive: { $ne: false } });

        console.log(`✅ Users in new DB: ${newCount}`);
        console.log(`✅ Active users: ${newActiveUsers}`);

        if (newCount !== count) {
            console.log(`⚠️  Count mismatch! Expected ${count}, got ${newCount}`);
        }

        await mongoose.disconnect();

        return { success: true, count: newCount, expected: count };

    } catch (error) {
        console.error(`❌ Error migrating users: ${error.message}`);
        try {
            await mongoose.disconnect();
        } catch (e) {
            // Ignore
        }
        return { success: false, error: error.message };
    }
}

async function migrateAllCollections() {
    const drop = process.argv.includes('--drop');

    console.log('🚀 MongoDB Collection Migration');
    console.log('═'.repeat(60));
    console.log(`Source (Old): ${OLD_URI.replace(/:[^:@]+@/, ':****@')}`);
    console.log(`Target (New): ${NEW_URI.replace(/:[^:@]+@/, ':****@')}`);
    console.log(`Drop existing: ${drop ? '✅ Yes' : '❌ No (skip if exists)'}`);
    console.log('═'.repeat(60));

    if (drop) {
        console.log('\n⚠️  WARNING: --drop flag is set. Existing collections will be DELETED!');
        console.log('   Press Ctrl+C within 5 seconds to cancel...\n');
        await new Promise(resolve => setTimeout(resolve, 5000));
    }

    try {
        // Get collections from old database
        console.log('\n📋 Step 1: Getting collections from old database...');
        const collections = await getCollections(OLD_URI);
        console.log(`✅ Found ${collections.length} collections: ${collections.join(', ')}`);

        // Migrate users first (most important)
        console.log('\n📋 Step 2: Migrating USERS collection...');
        const usersResult = await migrateUsersCollection(OLD_URI, NEW_URI, { drop });

        if (!usersResult.success && !usersResult.skipped) {
            console.error('\n❌ Users migration failed. Aborting other migrations.');
            process.exit(1);
        }

        // Migrate other collections
        console.log('\n📋 Step 3: Migrating other collections...');
        const otherCollections = collections.filter(c => c !== 'users');
        const results = [];

        for (const collectionName of otherCollections) {
            const result = await migrateCollection(OLD_URI, NEW_URI, collectionName, { drop, preserveIds: true });
            results.push({ collection: collectionName, ...result });
        }

        // Summary
        console.log('\n' + '═'.repeat(60));
        console.log('📊 Migration Summary');
        console.log('═'.repeat(60));

        console.log(`\n👥 Users: ${usersResult.success ? '✅ Success' : usersResult.skipped ? '⏭️  Skipped' : '❌ Failed'}`);
        if (usersResult.count !== undefined) {
            console.log(`   Migrated: ${usersResult.count} users`);
        }

        console.log(`\n📦 Other Collections:`);
        results.forEach(r => {
            const status = r.success ? '✅' : r.skipped ? '⏭️' : '❌';
            console.log(`   ${status} ${r.collection}: ${r.count || 0} documents`);
        });

        const successful = results.filter(r => r.success).length;
        const failed = results.filter(r => !r.success && !r.skipped).length;
        const skipped = results.filter(r => r.skipped).length;

        console.log(`\n📈 Total: ${successful} successful, ${skipped} skipped, ${failed} failed`);

        if (failed === 0) {
            console.log('\n✅ Migration completed successfully!');
            console.log('💡 Remember to test the new database before switching production.');
        } else {
            console.log('\n⚠️  Migration completed with some failures.');
            console.log('💡 Review the errors above and retry failed collections if needed.');
        }

    } catch (error) {
        console.error('\n❌ Migration failed:', error.message);
        process.exit(1);
    }
}

// Main execution
if (require.main === module) {
    migrateAllCollections()
        .then(() => {
            process.exit(0);
        })
        .catch((error) => {
            console.error('Fatal error:', error);
            process.exit(1);
        });
}

module.exports = { migrateAllCollections, migrateUsersCollection, migrateCollection };

