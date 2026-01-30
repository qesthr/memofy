const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

// Set timezone to Philippine Standard Time
process.env.TZ = 'Asia/Manila';

const CalendarEvent = require('../models/CalendarEvent');

async function deleteAllEvents() {
    try {
        // Try connecting to memofy_db specifically
        const mongoUri = process.env.MONGODB_URI || 'mongodb://localhost:27017/buksu-memo';

        // If MONGODB_URI contains a database name, replace it with memofy_db
        let memofyDbUri = mongoUri;
        if (mongoUri.includes('/')) {
            const parts = mongoUri.split('/');
            parts[parts.length - 1] = 'memofy_db';
            memofyDbUri = parts.join('/');
        } else {
            memofyDbUri = mongoUri.replace(/\/[^\/]+$/, '/memofy_db');
        }

        console.log(`🔗 Attempting to connect to: ${memofyDbUri}`);

        await mongoose.connect(memofyDbUri);

        const dbName = mongoose.connection.db.databaseName;
        console.log(`✅ Connected to MongoDB`);
        console.log(`📦 Database: ${dbName}`);

        // First, count existing events
        const countBefore = await CalendarEvent.countDocuments({});
        console.log(`\n📊 Found ${countBefore} calendar event(s) in the database`);

        if (countBefore === 0) {
            console.log('ℹ️  No events to delete');
            // Also check buksu-memo database
            await mongoose.connection.close();
            console.log('\n🔄 Checking buksu-memo database...');
            await mongoose.connect(process.env.MONGODB_URI || 'mongodb://localhost:27017/buksu-memo');
            const dbName2 = mongoose.connection.db.databaseName;
            console.log(`📦 Database: ${dbName2}`);
            const countBefore2 = await CalendarEvent.countDocuments({});
            console.log(`📊 Found ${countBefore2} calendar event(s) in ${dbName2}`);
            await mongoose.connection.close();
            process.exit(0);
        }

        // Delete all calendar events
        console.log(`\n🗑️  Deleting all calendar events...`);
        const result = await CalendarEvent.deleteMany({});

        console.log(`\n🗑️  Deleted ${result.deletedCount} calendar event(s) from the database`);
        console.log('✅ All calendar events have been successfully deleted\n');

        // Verify deletion
        const countAfter = await CalendarEvent.countDocuments({});
        console.log(`📊 Remaining events: ${countAfter}`);

        await mongoose.connection.close();
        console.log('✅ Database connection closed');
        process.exit(0);
    } catch (error) {
        console.error('❌ Error deleting events:', error);
        // Try the default database as fallback
        try {
            console.log('\n🔄 Trying default database connection...');
            await mongoose.connect(process.env.MONGODB_URI || 'mongodb://localhost:27017/buksu-memo');
            const dbName = mongoose.connection.db.databaseName;
            console.log(`📦 Connected to: ${dbName}`);
            const countBefore = await CalendarEvent.countDocuments({});
            console.log(`📊 Found ${countBefore} calendar event(s)`);
            if (countBefore > 0) {
                const result = await CalendarEvent.deleteMany({});
                console.log(`🗑️  Deleted ${result.deletedCount} calendar event(s)`);
            }
            await mongoose.connection.close();
        } catch (fallbackError) {
            console.error('Fallback connection also failed:', fallbackError);
        }
        process.exit(1);
    }
}

deleteAllEvents();
