const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

async function deleteCalendarEventsDirect() {
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

        // Access the collection directly using native MongoDB driver
        const db = mongoose.connection.db;
        const collection = db.collection('calendarevents');

        // Count documents directly
        const countBefore = await collection.countDocuments({});
        console.log(`📊 Found ${countBefore} document(s) in 'calendarevents' collection`);

        if (countBefore === 0) {
            console.log('ℹ️  No events found to delete');
            await mongoose.connection.close();
            process.exit(0);
        }

        // Show what we're about to delete
        console.log(`\n🗑️  Deleting all events from 'calendarevents' collection...`);

        // Delete all documents
        const result = await collection.deleteMany({});

        console.log(`\n✅ Deleted ${result.deletedCount} document(s)`);

        // Verify deletion
        const countAfter = await collection.countDocuments({});
        console.log(`📊 Remaining documents: ${countAfter}`);

        await mongoose.connection.close();
        console.log('\n✅ Database connection closed');
        process.exit(0);
    } catch (error) {
        console.error('❌ Error:', error);
        process.exit(1);
    }
}

deleteCalendarEventsDirect();

