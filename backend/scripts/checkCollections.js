const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

async function checkCollections() {
    try {
        // Connect to memofy_db
        const mongoUri = process.env.MONGODB_URI || 'mongodb://localhost:27017/buksu-memo';
        let memofyDbUri = mongoUri;
        if (mongoUri.includes('/')) {
            const parts = mongoUri.split('/');
            parts[parts.length - 1] = 'memofy_db';
            memofyDbUri = parts.join('/');
        }

        await mongoose.connect(memofyDbUri);
        const dbName = mongoose.connection.db.databaseName;
        console.log(`✅ Connected to MongoDB`);
        console.log(`📦 Database: ${dbName}\n`);

        // List all collections
        const collections = await mongoose.connection.db.listCollections().toArray();
        console.log(`📋 Collections in ${dbName}:`);
        collections.forEach((col, idx) => {
            console.log(`   ${idx + 1}. ${col.name}`);
        });

        // Check for calendarevents or similar collections
        const calendarCollections = collections.filter(col =>
            col.name.toLowerCase().includes('calendar') ||
            col.name.toLowerCase().includes('event')
        );

        if (calendarCollections.length > 0) {
            console.log(`\n📅 Calendar-related collections found:`);
            calendarCollections.forEach(col => {
                console.log(`   - ${col.name}`);
            });
        }

        // For each calendar-related collection, count documents
        for (const col of calendarCollections) {
            const collection = mongoose.connection.db.collection(col.name);
            const count = await collection.countDocuments({});
            console.log(`\n   📊 ${col.name}: ${count} document(s)`);

            if (count > 0 && count <= 20) {
                // Show sample documents
                const samples = await collection.find({}).limit(3).toArray();
                console.log(`   📄 Sample documents:`);
                samples.forEach((doc, idx) => {
                    console.log(`      ${idx + 1}. ID: ${doc._id}, Title: ${doc.title || doc.summary || 'N/A'}`);
                });
            }
        }

        // Also check the standard CalendarEvent model
        const CalendarEvent = require('../models/CalendarEvent');
        const count = await CalendarEvent.countDocuments({});
        console.log(`\n📊 CalendarEvent model count: ${count} document(s)`);

        await mongoose.connection.close();
        console.log('\n✅ Database connection closed');
        process.exit(0);
    } catch (error) {
        console.error('❌ Error:', error);
        process.exit(1);
    }
}

checkCollections();

