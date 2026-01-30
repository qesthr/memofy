const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

async function findCalendarEvents() {
    try {
        const baseUri = process.env.MONGODB_URI || 'mongodb://localhost:27017';
        const mongoUri = baseUri.includes('/') ? baseUri.split('/').slice(0, 3).join('/') : baseUri;

        await mongoose.connect(mongoUri);
        console.log(`✅ Connected to MongoDB\n`);

        // Check all databases that have calendarevents collection
        const databasesToCheck = ['memofy_db', 'buksu', 'buksu-memo', 'buksu_memo'];

        console.log(`🔍 Checking calendars collections in multiple databases...\n`);

        for (const dbName of databasesToCheck) {
            try {
                const db = mongoose.connection.useDb(dbName).db;
                const collection = db.collection('calendarevents');
                const count = await collection.countDocuments({});

                if (count > 0) {
                    console.log(`\n📦 Database: ${dbName}`);
                    console.log(`   📅 calendarevents: ${count} document(s)`);

                    // Show sample events
                    const events = await collection.find({}).limit(5).toArray();
                    events.forEach((event, idx) => {
                        console.log(`\n   Event ${idx + 1}:`);
                        console.log(`      ID: ${event._id}`);
                        console.log(`      Title: ${event.title || 'N/A'}`);
                        if (event.start) {console.log(`      Start: ${event.start}`);}
                        if (event.end) {console.log(`      End: ${event.end}`);}
                        if (event.createdBy) {console.log(`      Created By: ${event.createdBy}`);}
                    });
                }
            } catch (err) {
                // Skip if database doesn't exist or collection doesn't exist
            }
        }

        // Also check if there's a database literally called "memofy"
        try {
            const memofyDb = mongoose.connection.useDb('memofy').db;
            const collections = await memofyDb.listCollections().toArray();
            console.log(`\n\n📦 Database: memofy`);
            console.log(`   Collections: ${collections.length}`);
            collections.forEach(col => {
                console.log(`      - ${col.name}`);
            });

            if (collections.length > 0) {
                for (const col of collections) {
                    const collection = memofyDb.collection(col.name);
                    const count = await collection.countDocuments({});
                    console.log(`\n   ${col.name}: ${count} document(s)`);

                    if (count > 0 && count <= 10) {
                        const docs = await collection.find({}).limit(3).toArray();
                        docs.forEach((doc, idx) => {
                            console.log(`\n      Doc ${idx + 1}:`);
                            console.log(`         ID: ${doc._id}`);
                            if (doc.title) {console.log(`         Title: ${doc.title}`);}
                        });
                    }
                }
            }
        } catch (err) {
            console.log(`\n⚠️  Database 'memofy' does not exist or cannot be accessed`);
        }

        await mongoose.connection.close();
        console.log('\n✅ Database connection closed');
        process.exit(0);
    } catch (error) {
        console.error('❌ Error:', error.message);
        process.exit(1);
    }
}

findCalendarEvents();

