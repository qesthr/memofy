const mongoose = require('mongoose');
require('dotenv').config();

/**
 * Direct Migration Script
 * Migrates all collections from old DB to new DB
 */

// OLD database (Hong Kong - current)
const OLD_URI = process.env.MONGODB_URI ||
    'mongodb+srv://memofy_db:memofydb=@cluster0.ailayze.mongodb.net/memofy?retryWrites=true&w=majority&appName=Cluster0';

// NEW database (Singapore - new account) - from user
const NEW_URI = process.env.MONGODB_URI_SECONDARY ||
    process.env.MONGODB_URI_NEW ||
    'mongodb+srv://2301107552_db_user:Joenil20@memofy.y3m50tg.mongodb.net/memofy?appName=Memofy';

console.log('🚀 MongoDB Collection Migration');
console.log('═'.repeat(70));
console.log('Source (Old):', OLD_URI.replace(/:[^:@]+@/, ':****@'));
console.log('Target (New):', NEW_URI.replace(/:[^:@]+@/, ':****@'));
console.log('═'.repeat(70));

const drop = process.argv.includes('--drop');

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
    const { drop = false } = options;

    console.log(`\n📦 Migrating: ${collectionName}`);
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

        const count = await oldCollection.countDocuments();
        console.log(`   📊 Old DB: ${count} documents`);

        if (count === 0) {
            console.log(`   ⏭️  Empty collection, skipping...`);
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

        // Drop if requested
        if (drop) {
            console.log(`   🗑️  Dropping existing collection...`);
            await newCollection.drop().catch(() => {});
        } else {
            const existingCount = await newCollection.countDocuments();
            if (existingCount > 0) {
                console.log(`   ⚠️  Already has ${existingCount} documents (use --drop to replace)`);
                await mongoose.disconnect();
                return { success: false, skipped: true, reason: 'Collection exists' };
            }
        }

        // Insert documents in batches
        console.log(`   📤 Inserting ${documents.length} documents...`);

        if (documents.length > 0) {
            const batchSize = 1000;
            let inserted = 0;

            for (let i = 0; i < documents.length; i += batchSize) {
                const batch = documents.slice(i, i + batchSize);
                await newCollection.insertMany(batch, { ordered: false });
                inserted += batch.length;
                if (documents.length > 100) {
                    console.log(`   ⏳ ${inserted}/${documents.length} (${Math.round(inserted/documents.length*100)}%)`);
                }
            }
        }

        // Verify
        const newCount = await newCollection.countDocuments();
        console.log(`   ✅ New DB: ${newCount} documents`);

        if (newCount !== count) {
            console.log(`   ⚠️  Count mismatch! Expected ${count}, got ${newCount}`);
        }

        await mongoose.disconnect();
        return { success: true, count: newCount, expected: count };

    } catch (error) {
        console.error(`   ❌ Error: ${error.message}`);
        try {
            await mongoose.disconnect();
        } catch (e) {}
        return { success: false, error: error.message };
    }
}

async function migrateUsersCollection(oldURI, newURI, options = {}) {
    const { drop = false } = options;

    console.log(`\n👥 Migrating USERS collection (Priority)`);
    console.log('═'.repeat(60));

    try {
        await mongoose.connect(oldURI, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const oldDb = mongoose.connection.db;
        const oldUsers = oldDb.collection('users');

        const count = await oldUsers.countDocuments();
        console.log(`📊 Total users: ${count}`);

        if (count === 0) {
            console.log(`⏭️  No users found, skipping...`);
            await mongoose.disconnect();
            return { success: true, count: 0 };
        }

        const users = await oldUsers.find({}).toArray();

        const activeUsers = users.filter(u => u.isActive !== false).length;
        const admins = users.filter(u => u.role === 'admin').length;
        const secretaries = users.filter(u => u.role === 'secretary').length;
        const faculty = users.filter(u => u.role === 'faculty').length;

        console.log(`   Active: ${activeUsers} | Admins: ${admins} | Secretaries: ${secretaries} | Faculty: ${faculty}`);

        await mongoose.disconnect();

        await mongoose.connect(newURI, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const newDb = mongoose.connection.db;
        const newUsers = newDb.collection('users');

        if (drop) {
            console.log(`🗑️  Dropping existing users...`);
            await newUsers.drop().catch(() => {});
        } else {
            const existingCount = await newUsers.countDocuments();
            if (existingCount > 0) {
                console.log(`⚠️  Already has ${existingCount} users (use --drop to replace)`);
                await mongoose.disconnect();
                return { success: false, skipped: true };
            }
        }

        console.log(`📤 Inserting ${users.length} users...`);

        if (users.length > 0) {
            const batchSize = 500;
            let inserted = 0;

            for (let i = 0; i < users.length; i += batchSize) {
                const batch = users.slice(i, i + batchSize);
                await newUsers.insertMany(batch, { ordered: false });
                inserted += batch.length;
                if (users.length > 50) {
                    console.log(`   ⏳ ${inserted}/${users.length} (${Math.round(inserted/users.length*100)}%)`);
                }
            }
        }

        const newCount = await newUsers.countDocuments();
        const newActiveUsers = await newUsers.countDocuments({ isActive: { $ne: false } });

        console.log(`✅ Migrated: ${newCount} users (${newActiveUsers} active)`);

        await mongoose.disconnect();
        return { success: true, count: newCount, expected: count };

    } catch (error) {
        console.error(`❌ Error: ${error.message}`);
        try {
            await mongoose.disconnect();
        } catch (e) {}
        return { success: false, error: error.message };
    }
}

async function runMigration() {
    if (drop) {
        console.log('\n⚠️  WARNING: --drop flag is set. Existing collections will be DELETED!');
        console.log('   Press Ctrl+C within 5 seconds to cancel...\n');
        await new Promise(resolve => setTimeout(resolve, 5000));
    }

    try {
        console.log('\n📋 Step 1: Getting collections from old database...');
        const collections = await getCollections(OLD_URI);
        console.log(`✅ Found ${collections.length} collections: ${collections.join(', ')}`);

        console.log('\n📋 Step 2: Migrating USERS collection...');
        const usersResult = await migrateUsersCollection(OLD_URI, NEW_URI, { drop });

        if (!usersResult.success && !usersResult.skipped) {
            console.error('\n❌ Users migration failed. Aborting.');
            process.exit(1);
        }

        console.log('\n📋 Step 3: Migrating other collections...');
        const otherCollections = collections.filter(c => c !== 'users');
        const results = [];

        for (const collectionName of otherCollections) {
            const result = await migrateCollection(OLD_URI, NEW_URI, collectionName, { drop });
            results.push({ collection: collectionName, ...result });
        }

        // Summary
        console.log('\n' + '═'.repeat(70));
        console.log('📊 Migration Summary');
        console.log('═'.repeat(70));

        console.log(`\n👥 Users: ${usersResult.success ? '✅' : usersResult.skipped ? '⏭️' : '❌'} ${usersResult.count || 0} users`);

        console.log(`\n📦 Other Collections:`);
        results.forEach(r => {
            const status = r.success ? '✅' : r.skipped ? '⏭️' : '❌';
            console.log(`   ${status} ${r.collection.padEnd(20)} ${(r.count || 0).toString().padStart(6)} documents`);
        });

        const successful = results.filter(r => r.success).length;
        const failed = results.filter(r => !r.success && !r.skipped).length;
        const skipped = results.filter(r => r.skipped).length;

        console.log(`\n📈 Results: ${successful} successful, ${skipped} skipped, ${failed} failed`);

        if (failed === 0 && usersResult.success) {
            console.log('\n✅ Migration completed successfully!');
            console.log('💡 Update your .env file to use the new database.');
        } else {
            console.log('\n⚠️  Migration completed with some issues.');
        }

    } catch (error) {
        console.error('\n❌ Migration failed:', error.message);
        process.exit(1);
    }
}

runMigration();

