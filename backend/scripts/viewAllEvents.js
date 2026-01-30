const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

// Set timezone to Philippine Standard Time
process.env.TZ = 'Asia/Manila';

const CalendarEvent = require('../models/CalendarEvent');
const User = require('../models/User');

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

async function viewAllEvents() {
    try {
        // Connect to MongoDB
        await mongoose.connect(process.env.MONGODB_URI || 'mongodb://localhost:27017/buksu-memo');
        console.log('✅ Connected to MongoDB\n');

        // Get all calendar events
        const events = await CalendarEvent.find({})
            .populate('createdBy', 'firstName lastName email')
            .sort({ start: 1 })
            .lean();

        console.log(`📅 Total Calendar Events: ${events.length}\n`);

        if (events.length === 0) {
            console.log('No events found in the database.');
        } else {
            console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            events.forEach((event, index) => {
                console.log(`\n📍 Event #${index + 1}`);
                console.log(`   ID: ${event._id}`);
                console.log(`   Title: ${event.title}`);
                console.log(`   Category: ${event.category || 'standard'}`);
                console.log(`   All Day: ${event.allDay ? 'Yes' : 'No'}`);
                console.log(`   Created By: ${event.createdBy ? `${event.createdBy.firstName} ${event.createdBy.lastName} (${event.createdBy.email})` : 'N/A'}`);

                console.log(`\n   📅 Dates (Philippine Standard Time):`);
                console.log(`      Start: ${formatDatePH(event.start)}`);
                console.log(`      End: ${formatDatePH(event.end)}`);
                console.log(`      Start (Short): ${formatDateShort(event.start)}`);
                console.log(`      End (Short): ${formatDateShort(event.end)}`);

                console.log(`\n   📅 Dates (Raw UTC):`);
                console.log(`      Start UTC: ${event.start ? new Date(event.start).toISOString() : 'N/A'}`);
                console.log(`      End UTC: ${event.end ? new Date(event.end).toISOString() : 'N/A'}`);

                if (event.description) {
                    console.log(`\n   Description: ${event.description.substring(0, 100)}${event.description.length > 100 ? '...' : ''}`);
                }

                if (event.participants && (event.participants.departments?.length > 0 || event.participants.emails?.length > 0)) {
                    console.log(`\n   Participants:`);
                    if (event.participants.departments?.length > 0) {
                        console.log(`      Departments: ${event.participants.departments.join(', ')}`);
                    }
                    if (event.participants.emails?.length > 0) {
                        console.log(`      Emails: ${event.participants.emails.join(', ')}`);
                    }
                }

                console.log(`\n   Status: ${event.status || 'scheduled'}`);
                console.log(`   Created At: ${formatDatePH(event.createdAt)}`);
                console.log(`   Updated At: ${formatDatePH(event.updatedAt)}`);

                if (index < events.length - 1) {
                    console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
                }
            });

            console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        }

        await mongoose.connection.close();
        console.log('\n✅ Database connection closed');
        process.exit(0);
    } catch (error) {
        console.error('❌ Error viewing events:', error);
        process.exit(1);
    }
}

viewAllEvents();

