const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

async function listAllDatabases() {
    try {
        // Connect to MongoDB (without specifying database)
        const baseUri = process.env.MONGODB_URI || 'mongodb://localhost:27017';
        const mongoUri = baseUri.includes('/') ? baseUri.split('/').slice(0, 3).join('/') : baseUri;

        console.log(`🔗 Connecting to: ${mongoUri}`);
        await mongoose.connect(mongoUri);
        console.log(`✅ Connected to MongoDB\n`);

        // List all databases
        const adminDb = mongoose.connection.db.admin();
        const { databases } = await adminDb.listDatabases();

        console.log(`📊 Available databases:`);
        console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);

        for (const dbInfo of databases) {
            console.log(`\n📦 Database: ${dbInfo.name}`);
            console.log(`   Size: ${(dbInfo.sizeOnDisk / 1024 / 1024).toFixed(2)} MB`);

            // Check collections in this database
            const db = mongoose.connection.useDb(dbInfo.name).db;
            try {
                const collections = await db.listCollections().toArray();
                console.log(`   Collections: ${collections.length}`);
                if (collections.length > 0) {
                    collections.forEach((col, idx) => {
                        console.log(`      ${idx + 1}. ${col.name}`);
                    });

                    // Check calendarevents collection specifically
                    const calendarCol = collections.find(c => c.name.toLowerCase().includes('calendar') || c.name.toLowerCase().includes('event'));
                    if (calendarCol) {
                        const collection = db.collection(calendarCol.name);
                        const count = await collection.countDocuments({});
                        console.log(`\n   📅 ${calendarCol.name}: ${count} document(s)`);
                    }
                }
            } catch (err) {
                console.log(`   ⚠️  Could not list collections: ${err.message}`);
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

listAllDatabases();

