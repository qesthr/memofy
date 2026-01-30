const mongoose = require('mongoose');

async function deleteAllCalendarEvents() {
    try {
        // MongoDB Atlas connection string
        const mongoUri = 'mongodb+srv://memofy_db:memofydb=@cluster0.ailayze.mongodb.net/memofy?retryWrites=true&w=majority&appName=Cluster0';

        console.log(`🔗 Connecting to MongoDB Atlas...`);
        await mongoose.connect(mongoUri);

        const dbName = mongoose.connection.db.databaseName;
        console.log(`✅ Connected to MongoDB`);
        console.log(`📦 Database: ${dbName}\n`);

        // List all collections first
        const db = mongoose.connection.db;
        const collections = await db.listCollections().toArray();
        console.log(`📋 Collections in '${dbName}' database:`);
        collections.forEach((col, idx) => {
            console.log(`   ${idx + 1}. ${col.name}`);
        });

        // Check calendarevents collection
        const collection = db.collection('calendarevents');
        const countBefore = await collection.countDocuments({});
        console.log(`\n📊 Found ${countBefore} document(s) in 'calendarevents' collection`);

        if (countBefore === 0) {
            console.log('ℹ️  No events found to delete');
            await mongoose.connection.close();
            process.exit(0);
        }

        // Show what we're about to delete
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
        if (error.message.includes('authentication')) {
            console.error('\n💡 Authentication failed. Please check:');
            console.error('   1. MongoDB Atlas username and password are correct');
            console.error('   2. IP address is whitelisted in MongoDB Atlas');
            console.error('   3. Database user has proper permissions');
        }
        process.exit(1);
    }
}

deleteAllCalendarEvents();

