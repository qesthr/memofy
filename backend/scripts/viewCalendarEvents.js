const mongoose = require('mongoose');
const path = require('path');

// Load environment variables - try multiple paths
require('dotenv').config({ path: path.join(__dirname, '../.env') });
require('dotenv').config({ path: path.join(__dirname, '../../.env') });
require('dotenv').config();

const CalendarEvent = require('../models/CalendarEvent');
const User = require('../models/User');

async function viewCalendarEvents() {
    try {
        // Connect to MongoDB - use fallback if MONGODB_URI not found
        const mongoUri = process.env.MONGODB_URI || process.env.MONGO_URI || 'mongodb://localhost:27017/buksu-memo';
        console.log(`🔌 Connecting to MongoDB...`);
        await mongoose.connect(mongoUri);
        console.log('✅ Connected to MongoDB');
        console.log(`📊 Database: ${mongoose.connection.db.databaseName}\n`);

        // Count total events
        const totalCount = await CalendarEvent.countDocuments();
        console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
        console.log(`📅 CALENDAR EVENTS COLLECTION SUMMARY`);
        console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
        console.log(`\n📊 Total Events: ${totalCount}\n`);

        if (totalCount === 0) {
            console.log('ℹ️  No calendar events found in the database.');
            await mongoose.disconnect();
            return;
        }

        // Count by status
        const activeCount = await CalendarEvent.countDocuments({ archived: { $ne: true } });
        const archivedCount = await CalendarEvent.countDocuments({ archived: true });
        const deletedCount = await CalendarEvent.countDocuments({ status: 'cancelled' });

        console.log(`📈 Event Statistics:`);
        console.log(`   ✅ Active Events: ${activeCount}`);
        console.log(`   📦 Archived Events: ${archivedCount}`);
        console.log(`   ❌ Cancelled Events: ${deletedCount}`);
        console.log(`   📊 Total: ${totalCount}\n`);

        // Count by category
        const categoryCounts = await CalendarEvent.aggregate([
            {
                $group: {
                    _id: '$category',
                    count: { $sum: 1 }
                }
            },
            { $sort: { count: -1 } }
        ]);

        console.log(`📋 Events by Category:`);
        categoryCounts.forEach(cat => {
            console.log(`   ${cat._id || 'standard'}: ${cat.count}`);
        });
        console.log('');

        // Get recent events (last 10)
        const recentEvents = await CalendarEvent.find()
            .sort({ createdAt: -1 })
            .limit(10)
            .populate('createdBy', 'firstName lastName email')
            .lean();

        console.log(`📅 Recent Events (Last 10):`);
        console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
        recentEvents.forEach((event, index) => {
            const creator = event.createdBy ?
                `${event.createdBy.firstName || ''} ${event.createdBy.lastName || ''}`.trim() || event.createdBy.email :
                'Unknown';
            const archivedStatus = event.archived ? '📦 ARCHIVED' : '✅ Active';
            const startDate = new Date(event.start).toLocaleString('en-US', {
                timeZone: 'Asia/Manila',
                dateStyle: 'short',
                timeStyle: 'short'
            });

            console.log(`\n${index + 1}. ${event.title}`);
            console.log(`   ID: ${event._id}`);
            console.log(`   Status: ${archivedStatus}`);
            console.log(`   Category: ${event.category || 'standard'}`);
            console.log(`   Start: ${startDate}`);
            console.log(`   Created By: ${creator}`);
            console.log(`   Created At: ${new Date(event.createdAt).toLocaleString('en-US', { timeZone: 'Asia/Manila' })}`);
            if (event.archived && event.archivedAt) {
                console.log(`   Archived At: ${new Date(event.archivedAt).toLocaleString('en-US', { timeZone: 'Asia/Manila' })}`);
            }
        });

        // Get archived events
        if (archivedCount > 0) {
            const archivedEvents = await CalendarEvent.find({ archived: true })
                .sort({ archivedAt: -1 })
                .limit(5)
                .populate('createdBy', 'firstName lastName email')
                .lean();

            console.log(`\n\n📦 Archived Events (Last 5):`);
            console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
            archivedEvents.forEach((event, index) => {
                const creator = event.createdBy ?
                    `${event.createdBy.firstName || ''} ${event.createdBy.lastName || ''}`.trim() || event.createdBy.email :
                    'Unknown';
                const archivedDate = event.archivedAt ?
                    new Date(event.archivedAt).toLocaleString('en-US', { timeZone: 'Asia/Manila' }) :
                    'Unknown';

                console.log(`\n${index + 1}. ${event.title}`);
                console.log(`   ID: ${event._id}`);
                console.log(`   Archived At: ${archivedDate}`);
                console.log(`   Created By: ${creator}`);
            });
        }

        console.log(`\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━`);
        console.log(`✅ Summary complete!`);
        console.log(`━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n`);

        await mongoose.disconnect();
        console.log('✅ Disconnected from MongoDB');
    } catch (error) {
        console.error('❌ Error:', error.message);
        console.error('Error stack:', error.stack);
        process.exit(1);
    }
}

viewCalendarEvents();

