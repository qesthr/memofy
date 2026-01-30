const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

async function deleteAllCalendarEvents() {
    try {
        // Get MongoDB URI from command line argument or use default
        const mongoUri = process.argv[2] || process.env.MONGODB_URI || 'mongodb://localhost:27017';

        // Connect to MongoDB (without specifying database)
        await mongoose.connect(mongoUri);

        // Switch to memofy_db database
        const db = mongoose.connection.useDb('memofy_db');
        console.log(`✅ Connected to MongoDB`);
        console.log(`📦 Using database: memofy_db\n`);

        // Access the calendarevents collection directly
        const collection = db.collection('calendarevents');

        // Count documents
        const countBefore = await collection.countDocuments({});
        console.log(`📊 Found ${countBefore} document(s) in 'calendarevents' collection`);

        if (countBefore === 0) {
            console.log('\n⚠️  No events found to delete.');
            console.log('💡 If you see 8 events in your MongoDB client, please check:');
            console.log('   1. Are you connected to the same MongoDB server?');
            console.log('   2. Is the database name exactly "memofy_db"?');
            console.log('   3. Is the collection name exactly "calendarevents"?');
            console.log('\n💡 You can provide a custom MongoDB URI as an argument:');
            console.log('   node backend/scripts/deleteAllCalendarEvents.js "mongodb://localhost:27017"');
            await mongoose.connection.close();
            process.exit(0);
        }

        // Confirm deletion
        console.log(`\n🗑️  About to delete ${countBefore} calendar event(s)...`);

        // Delete all documents
        const result = await collection.deleteMany({});

        console.log(`\n✅ Successfully deleted ${result.deletedCount} calendar event(s)`);

        // Verify deletion
        const countAfter = await collection.countDocuments({});
        console.log(`📊 Remaining documents: ${countAfter}`);

        if (countAfter === 0) {
            console.log('\n✅ All calendar events have been deleted successfully!');
        }

        await mongoose.connection.close();
        console.log('\n✅ Database connection closed');
        process.exit(0);
    } catch (error) {
        console.error('❌ Error:', error.message);
        console.error('\n💡 Make sure MongoDB is running and the connection string is correct.');
        process.exit(1);
    }
}

deleteAllCalendarEvents();

