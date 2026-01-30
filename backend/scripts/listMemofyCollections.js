const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

async function listCollectionsInMemofy() {
    try {
        // Connect directly to memofy database
        const baseUri = process.env.MONGODB_URI || 'mongodb://localhost:27017';

        // Parse the URI and replace database name with 'memofy'
        let mongoUri;
        if (baseUri.includes('/') && baseUri.split('/').length > 3) {
            // Has database name: mongodb://host:port/dbname
            const parts = baseUri.split('/');
            parts[parts.length - 1] = 'memofy';
            mongoUri = parts.join('/');
        } else {
            // No database name: mongodb://host:port
            mongoUri = `${baseUri}/memofy`;
        }

        console.log(`🔗 Connecting to: ${mongoUri}`);
        await mongoose.connect(mongoUri);
        console.log(`✅ Connected to MongoDB`);

        const dbName = mongoose.connection.db.databaseName;
        const db = mongoose.connection.db;
        console.log(`📦 Database: ${dbName}\n`);

        // List all collections
        const collections = await db.listCollections().toArray();
        console.log(`📋 Collections in 'memofy' database:`);
        console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);

        if (collections.length === 0) {
            console.log('   No collections found in this database.');
        } else {
            collections.forEach((col, idx) => {
                console.log(`   ${idx + 1}. ${col.name}`);
            });
        }

        // Check document counts for each collection
        if (collections.length > 0) {
            console.log(`\n📊 Document counts:`);
            console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);

            for (const col of collections) {
                const collection = db.collection(col.name);
                const count = await collection.countDocuments({});
                console.log(`   ${col.name}: ${count} document(s)`);
            }
        }

        // Check for calendar-related collections specifically
        const calendarCollections = collections.filter(col =>
            col.name.toLowerCase().includes('calendar') ||
            col.name.toLowerCase().includes('event')
        );

        if (calendarCollections.length > 0) {
            console.log(`\n📅 Calendar-related collections:`);
            console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
            for (const col of calendarCollections) {
                const collection = db.collection(col.name);
                const count = await collection.countDocuments({});
                console.log(`\n   📋 Collection: ${col.name}`);
                console.log(`   📊 Total documents: ${count}`);

                if (count > 0 && count <= 10) {
                    // Show all documents
                    const docs = await collection.find({}).limit(10).toArray();
                    console.log(`   📄 Documents:`);
                    docs.forEach((doc, idx) => {
                        console.log(`      ${idx + 1}. ID: ${doc._id}`);
                        console.log(`         Title: ${doc.title || doc.summary || 'N/A'}`);
                        if (doc.start) {console.log(`         Start: ${doc.start}`);}
                        if (doc.end) {console.log(`         End: ${doc.end}`);}
                    });
                } else if (count > 10) {
                    // Show sample
                    const samples = await collection.find({}).limit(3).toArray();
                    console.log(`   📄 Sample documents (showing 3 of ${count}):`);
                    samples.forEach((doc, idx) => {
                        console.log(`      ${idx + 1}. ID: ${doc._id}`);
                        console.log(`         Title: ${doc.title || doc.summary || 'N/A'}`);
                    });
                }
            }
        }

        await mongoose.connection.close();
        console.log('\n✅ Database connection closed');
        process.exit(0);
    } catch (error) {
        console.error('❌ Error:', error.message);
        console.error(error);
        process.exit(1);
    }
}

listCollectionsInMemofy();

