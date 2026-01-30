const mongoose = require('mongoose');
require('dotenv').config();

/**
 * Quick Migration Script with Explicit New URI
 * Use this if you want to specify the new URI directly
 */

// OLD database (Hong Kong - current)
const OLD_URI = process.env.MONGODB_URI_PRIMARY ||
    (process.env.MONGODB_URI && process.env.MONGODB_URI.includes('ailayze') ? process.env.MONGODB_URI : null) ||
    'mongodb+srv://memofy_db:memofydb=@cluster0.ailayze.mongodb.net/memofy?retryWrites=true&w=majority&appName=Cluster0';

// NEW database (Singapore - new account)
const NEW_URI = process.env.MONGODB_URI_SECONDARY ||
    process.env.MONGODB_URI_NEW ||
    process.env.MONGODB_URI_SINGAPORE ||
    // New URI provided by user
    'mongodb+srv://2301107552_db_user:3c7LVflxbzh2VPzI=@memofy.y3m50tg.mongodb.net/memofy?appName=Memofy';

console.log('🚀 MongoDB Migration - Using Explicit URIs');
console.log('═'.repeat(60));
console.log('OLD (Source):', OLD_URI.replace(/:[^:@]+@/, ':****@'));
console.log('NEW (Target):', NEW_URI.replace(/:[^:@]+@/, ':****@'));
console.log('═'.repeat(60));

// Import migration functions
const { migrateAllCollections, migrateUsersCollection, migrateCollection, getCollections } = require('./migrate-collections');

// Override the URI getters
const originalModule = require('./migrate-collections');
const OLD_URI_FIXED = OLD_URI;
const NEW_URI_FIXED = NEW_URI;

async function runMigration() {
    const drop = process.argv.includes('--drop');

    try {
        // Get collections from old database
        console.log('\n📋 Step 1: Getting collections from old database...');
        const collections = await getCollections(OLD_URI_FIXED);
        console.log(`✅ Found ${collections.length} collections: ${collections.join(', ')}`);

        // Migrate users first
        console.log('\n📋 Step 2: Migrating USERS collection...');
        const usersResult = await migrateUsersCollection(OLD_URI_FIXED, NEW_URI_FIXED, { drop });

        if (!usersResult.success && !usersResult.skipped) {
            console.error('\n❌ Users migration failed. Aborting.');
            process.exit(1);
        }

        // Migrate other collections
        console.log('\n📋 Step 3: Migrating other collections...');
        const otherCollections = collections.filter(c => c !== 'users');
        const results = [];

        for (const collectionName of otherCollections) {
            const result = await migrateCollection(OLD_URI_FIXED, NEW_URI_FIXED, collectionName, { drop, preserveIds: true });
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
        } else {
            console.log('\n⚠️  Migration completed with some failures.');
        }

    } catch (error) {
        console.error('\n❌ Migration failed:', error.message);
        process.exit(1);
    }
}

runMigration();

