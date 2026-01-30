const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

// Set timezone to Philippine Standard Time
process.env.TZ = 'Asia/Manila';

const User = require('../models/User');
const { google } = require('googleapis');

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

async function getAuthenticatedClient(user) {
    const oauth2 = new google.auth.OAuth2(
        process.env.GOOGLE_CALENDAR_CLIENT_ID,
        process.env.GOOGLE_CALENDAR_CLIENT_SECRET,
        process.env.GOOGLE_CALENDAR_REDIRECT_URI
    );

    oauth2.setCredentials({
        access_token: user.calendarAccessToken,
        refresh_token: user.calendarRefreshToken
    });

    oauth2.on('tokens', (tokens) => {
        if (tokens.access_token) {
            user.calendarAccessToken = tokens.access_token;
            user.save().catch(err => {
                console.error('Error saving refreshed token:', err);
            });
        }
    });

    return oauth2;
}

async function viewGoogleCalendarEvents() {
    try {
        // Connect to MongoDB
        await mongoose.connect(process.env.MONGODB_URI || 'mongodb://localhost:27017/buksu-memo');
        console.log('✅ Connected to MongoDB\n');

        // Find admin user - try different email formats
        let adminUser = await User.findOne({ email: 'admin@buksu.edu.ph' });

        if (!adminUser) {
            // Try case-insensitive search
            adminUser = await User.findOne({ email: /^admin@buksu\.edu\.ph$/i });
        }

        if (!adminUser) {
            // Show all users with calendar access
            const allUsers = await User.find({
                $or: [
                    { calendarAccessToken: { $exists: true, $ne: null } },
                    { calendarRefreshToken: { $exists: true, $ne: null } }
                ]
            }).select('email firstName lastName').lean();

            if (allUsers.length > 0) {
                console.log('❌ User admin@buksu.edu.ph not found, but found users with Google Calendar access:');
                allUsers.forEach(u => console.log(`   - ${u.email}`));
                console.log('\nPlease specify which user to check, or update the script with the correct email.');
            } else {
                console.log('❌ User admin@buksu.edu.ph not found in database');
                console.log('❌ No users with Google Calendar access found');
            }
            await mongoose.connection.close();
            process.exit(0);
        }

        console.log(`✅ Found user: ${adminUser.email}`);
        console.log(`   User ID: ${adminUser._id}`);
        console.log(`   Name: ${adminUser.firstName} ${adminUser.lastName}\n`);

        // Check if Google Calendar is configured
        const clientId = process.env.GOOGLE_CALENDAR_CLIENT_ID;
        const clientSecret = process.env.GOOGLE_CALENDAR_CLIENT_SECRET;

        if (!clientId || !clientSecret) {
            console.log('❌ Google Calendar OAuth is not configured');
            console.log('   Set GOOGLE_CALENDAR_CLIENT_ID and GOOGLE_CALENDAR_CLIENT_SECRET in .env');
            await mongoose.connection.close();
            process.exit(0);
        }

        // Check if user has connected their Google Calendar
        if (!adminUser.calendarAccessToken && !adminUser.calendarRefreshToken) {
            console.log('❌ Google Calendar is not connected for this user');
            console.log('   The user needs to connect their Google Calendar first');
            await mongoose.connection.close();
            process.exit(0);
        }

        console.log('📅 Fetching Google Calendar events...\n');

        // Get authenticated client
        const auth = await getAuthenticatedClient(adminUser);
        const calendar = google.calendar({ version: 'v3', auth });

        // Get current date range (last 30 days to next 30 days)
        const now = new Date();
        const timeMin = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000); // 30 days ago
        const timeMax = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000); // 30 days from now

        const timeMinFormatted = timeMin.toISOString();
        const timeMaxFormatted = timeMax.toISOString();

        console.log(`📅 Date Range: ${formatDateShort(timeMin)} to ${formatDateShort(timeMax)}`);
        console.log(`📅 API Request: ${timeMinFormatted} to ${timeMaxFormatted}\n`);

        // Fetch events from Google Calendar
        const resp = await calendar.events.list({
            calendarId: 'primary',
            timeMin: timeMinFormatted,
            timeMax: timeMaxFormatted,
            singleEvents: true,
            orderBy: 'startTime'
        });

        const events = resp.data.items || [];
        console.log(`📊 Found ${events.length} Google Calendar event(s)\n`);

        if (events.length === 0) {
            console.log('No Google Calendar events found in the specified date range.');
        } else {
            console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            events.forEach((event, index) => {
                console.log(`\n📍 Google Calendar Event #${index + 1}`);
                console.log(`   ID: ${event.id}`);
                console.log(`   Title: ${event.summary || '(No title)'}`);
                console.log(`   Status: ${event.status}`);
                console.log(`   Location: ${event.location || 'N/A'}`);

                if (event.description) {
                    const desc = event.description.substring(0, 100);
                    console.log(`   Description: ${desc}${event.description.length > 100 ? '...' : ''}`);
                }

                // Parse start date
                const startDateTime = event.start?.dateTime || event.start?.date;
                const startDate = startDateTime ? new Date(startDateTime) : null;

                // Parse end date
                const endDateTime = event.end?.dateTime || event.end?.date;
                const endDate = endDateTime ? new Date(endDateTime) : null;

                console.log(`\n   📅 Dates (Philippine Standard Time):`);
                if (startDate) {
                    console.log(`      Start: ${formatDatePH(startDate)}`);
                    console.log(`      Start (Short): ${formatDateShort(startDate)}`);
                }
                if (endDate) {
                    console.log(`      End: ${formatDatePH(endDate)}`);
                    console.log(`      End (Short): ${formatDateShort(endDate)}`);
                }

                console.log(`\n   📅 Dates (Raw):`);
                console.log(`      Start: ${startDateTime || 'N/A'}`);
                console.log(`      End: ${endDateTime || 'N/A'}`);
                console.log(`      All Day: ${event.start?.date ? 'Yes' : 'No'}`);

                if (event.attendees && event.attendees.length > 0) {
                    console.log(`\n   👥 Attendees: ${event.attendees.length}`);
                    event.attendees.forEach((attendee, idx) => {
                        console.log(`      ${idx + 1}. ${attendee.email} (${attendee.responseStatus || 'no response'})`);
                    });
                }

                console.log(`\n   🔗 Google Calendar Link: ${event.htmlLink || 'N/A'}`);

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
        console.error('❌ Error viewing Google Calendar events:', error);
        if (error.response && error.response.data) {
            console.error('Google Calendar API error response:', JSON.stringify(error.response.data, null, 2));
        }
        await mongoose.connection.close();
        process.exit(1);
    }
}

viewGoogleCalendarEvents();

