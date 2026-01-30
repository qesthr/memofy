/**
 * Script to list all collections in the database
 * Run with: node backend/scripts/listCollections.js
 */

require('dotenv').config();
const mongoose = require('mongoose');
const connectDB = require('../config/db');

async function listCollections() {
    try {
        // Connect to database
        await connectDB();
        console.log('✅ Connected to MongoDB\n');

        // Get database name from connection
        const db = mongoose.connection.db;
        const dbName = db.databaseName;
        console.log(`📊 Database: ${dbName}\n`);

        // List all collections
        const collections = await db.listCollections().toArray();

        console.log(`📋 Found ${collections.length} collections:\n`);
        console.log('='.repeat(80));

        // Get document counts for each collection
        const collectionStats = await Promise.all(
            collections.map(async (collection) => {
                const collectionName = collection.name;
                const count = await db.collection(collectionName).countDocuments();

                // Get sample document structure
                const sample = await db.collection(collectionName).findOne({});

                return {
                    name: collectionName,
                    count,
                    sample: sample ? Object.keys(sample).slice(0, 10) : [] // First 10 keys
                };
            })
        );

        // Sort by count (descending)
        collectionStats.sort((a, b) => b.count - a.count);

        // Display results
        collectionStats.forEach((stat, index) => {
            console.log(`\n[${index + 1}] Collection: ${stat.name}`);
            console.log(`    Document Count: ${stat.count.toLocaleString()}`);

            if (stat.sample.length > 0) {
                console.log(`    Sample Fields: ${stat.sample.join(', ')}${stat.sample.length >= 10 ? '...' : ''}`);
            } else {
                console.log(`    Sample Fields: (empty collection)`);
            }
            console.log('-'.repeat(80));
        });

        // Summary
        const totalDocuments = collectionStats.reduce((sum, stat) => sum + stat.count, 0);
        console.log(`\n📈 Summary:`);
        console.log(`    Total Collections: ${collections.length}`);
        console.log(`    Total Documents: ${totalDocuments.toLocaleString()}`);
        console.log(`    Average Documents per Collection: ${Math.round(totalDocuments / collections.length).toLocaleString()}`);

        // Expected collections based on models
        console.log(`\n📦 Expected Collections (from models):`);
        const expectedCollections = [
            'users',
            'memos',
            'calendarevents',
            'auditlogs',
            'activitylogs',
            'signatures',
            'systemsettings',
            'userlocks',
            'rollbacklogs'
        ];

        const existingCollectionNames = collections.map(c => c.name.toLowerCase());

        expectedCollections.forEach(expected => {
            const exists = existingCollectionNames.includes(expected);
            const actualName = collections.find(c => c.name.toLowerCase() === expected)?.name || 'N/A';
            const status = exists ? '✅' : '❌';
            console.log(`    ${status} ${expected} ${exists ? `(${actualName})` : '(not found)'}`);
        });

        // Collections not in expected list
        const unexpectedCollections = collections.filter(
            c => !expectedCollections.includes(c.name.toLowerCase())
        );

        if (unexpectedCollections.length > 0) {
            console.log(`\n⚠️  Unexpected Collections (not in models):`);
            unexpectedCollections.forEach(c => {
                console.log(`    - ${c.name} (${c.count} documents)`);
            });
        }

        console.log('\n✅ Collection listing complete!\n');

    } catch (error) {
        console.error('❌ Error listing collections:', error);
    } finally {
        await mongoose.connection.close();
        console.log('🔌 Database connection closed');
    }
}

// Run the listing
listCollections();

