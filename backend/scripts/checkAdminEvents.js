const mongoose = require('mongoose');
require('dotenv').config();
const CalendarEvent = require('../models/CalendarEvent');
const User = require('../models/User');

// Set timezone to Philippine Standard Time
process.env.TZ = 'Asia/Manila';

const connectDB = async () => {
    try {
        const conn = await mongoose.connect(process.env.MONGODB_URI);
        console.log(`MongoDB Connected: ${conn.connection.host}`);
        return conn;
    } catch (error) {
        console.error(`Error: ${error.message}`);
        process.exit(1);
    }
};

// Helper function to format dates in Philippine Standard Time
const formatDatePH = (date) => {
    if (!date) {return 'N/A';}
    return date.toLocaleString('en-US', {
        timeZone: 'Asia/Manila',
        dateStyle: 'full',
        timeStyle: 'long',
        hour12: true
    });
};

const formatDateShort = (date) => {
    if (!date) {return 'N/A';}
    return date.toLocaleString('en-US', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    });
};

async function checkAdminEvents() {
    try {
        await connectDB();

        console.log('\n🔍 Searching for admin@buksu.edu.ph...\n');

        // Find user with email admin@buksu.edu.ph
        const adminUser = await User.findOne({ email: 'admin@buksu.edu.ph' });

        if (!adminUser) {
            console.log('❌ User admin@buksu.edu.ph not found in database');
            process.exit(0);
        }

        console.log(`✅ Found user: ${adminUser.email}`);
        console.log(`   User ID: ${adminUser._id}`);
        console.log(`   Name: ${adminUser.firstName} ${adminUser.lastName}`);
        console.log(`   Role: ${adminUser.role}\n`);

        // Find all calendar events created by this user
        const events = await CalendarEvent.find({ createdBy: adminUser._id })
            .populate('createdBy', 'email firstName lastName')
            .populate('memoId', 'title')
            .sort({ start: 1 });

        console.log(`📊 Found ${events.length} calendar event(s) created by admin@buksu.edu.ph\n`);

        if (events.length === 0) {
            console.log('No calendar events found for this user.');
        } else {
            events.forEach((event, index) => {
                console.log(`\n${index + 1}. Event: "${event.title}"`);
                console.log(`   ID: ${event._id}`);
                console.log(`   Start: ${formatDateShort(event.start)} (Philippine Standard Time)`);
                console.log(`   End: ${formatDateShort(event.end)} (Philippine Standard Time)`);
                console.log(`   All Day: ${event.allDay}`);
                console.log(`   Category: ${event.category}`);
                console.log(`   Status: ${event.status}`);
                console.log(`   Created By: ${event.createdBy?.email || 'N/A'}`);
                console.log(`   Participants: ${JSON.stringify(event.participants)}`);
                console.log(`   Memo ID: ${event.memoId?._id || 'N/A'}`);
                console.log(`   Memo Title: ${event.memoId?.title || 'N/A'}`);
                console.log(`   Created At: ${formatDateShort(event.createdAt)} (Philippine Standard Time)`);
                console.log(`   Updated At: ${formatDateShort(event.updatedAt)} (Philippine Standard Time)`);
            });
        }

        // Also check for events with createdBy as string ID (legacy format)
        const eventsByStringId = await CalendarEvent.find({
            createdBy: adminUser._id.toString()
        })
            .populate('createdBy', 'email firstName lastName')
            .populate('memoId', 'title')
            .sort({ start: 1 });

        if (eventsByStringId.length > 0) {
            console.log(`\n⚠️  Found ${eventsByStringId.length} additional event(s) with string ID format\n`);
        }

        // Check for any events that might have mismatched createdBy references
        const allEvents = await CalendarEvent.find({}).lean();
        const adminIdStr = adminUser._id.toString();
        const mismatchedEvents = allEvents.filter(event => {
            const eventCreatorId = event.createdBy ?
                (event.createdBy._id ? event.createdBy._id.toString() : event.createdBy.toString()) :
                null;
            return eventCreatorId === adminIdStr && !event._id.equals(events.find(e => e._id.equals(event._id))?._id);
        });

        if (mismatchedEvents.length > 0) {
            console.log(`\n⚠️  Found ${mismatchedEvents.length} event(s) with potential ID mismatch issues\n`);
        }

        await mongoose.connection.close();
        console.log('\n✅ Database connection closed');
        process.exit(0);

    } catch (error) {
        console.error('❌ Error:', error);
        await mongoose.connection.close();
        process.exit(1);
    }
}

checkAdminEvents();

