const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

const Memo = require('../models/Memo');
const User = require('../models/User');

async function countMemos() {
    try {
        // Connect to MongoDB Atlas
        const mongoUri = 'mongodb+srv://memofy_db:memofydb=@cluster0.ailayze.mongodb.net/memofy?retryWrites=true&w=majority&appName=Cluster0';

        console.log(`🔗 Connecting to MongoDB Atlas...`);
        await mongoose.connect(mongoUri);

        const dbName = mongoose.connection.db.databaseName;
        console.log(`✅ Connected to MongoDB`);
        console.log(`📦 Database: ${dbName}\n`);

        // Get admin and secretary user IDs
        const adminSecretaryUsers = await User.find({
            role: { $in: ['admin', 'secretary'] }
        }).select('_id email role').lean();

        const adminSecretaryIds = adminSecretaryUsers.map(u => u._id);

        console.log(`👥 Found ${adminSecretaryUsers.length} admin/secretary users:`);
        adminSecretaryUsers.forEach(u => {
            console.log(`   - ${u.email} (${u.role})`);
        });
        console.log('');

        // System / non-memo activity types to exclude (keep in sync with reportService.getMemoFilter)
        const systemActivityTypes = [
            'user_activity',
            'system_notification',
            'user_deleted',
            'password_reset',
            'welcome_email',
            'user_profile_edited',
            'memo_received',
            'calendar_connected'
        ];

        // Filter for admin/secretary memos only (excluding system-generated and archived/deleted)
        const memoFilter = {
            sender: { $in: adminSecretaryIds },
            activityType: { $nin: systemActivityTypes },
            status: { $nin: ['archived', 'deleted'] }
        };

        // Count total memos (admin/secretary only, excluding system-generated)
        const totalMemos = await Memo.countDocuments(memoFilter);
        console.log(`📊 Total Memos (Admin/Secretary Only): ${totalMemos}\n`);

        // Count all memos for comparison
        const allMemos = await Memo.countDocuments({ status: { $ne: 'deleted' } });
        console.log(`📊 Total Memos (All Users): ${allMemos}`);
        console.log(`📊 Excluded System Memos: ${allMemos - totalMemos}\n`);

        if (totalMemos > 0) {
            // Count by status
            const statusCounts = await Memo.aggregate([
                { $match: memoFilter },
                {
                    $group: {
                        _id: '$status',
                        count: { $sum: 1 }
                    }
                },
                { $sort: { count: -1 } }
            ]);

            console.log('📋 Memos by Status:');
            statusCounts.forEach(item => {
                console.log(`   ${item._id || 'N/A'}: ${item.count}`);
            });

            // Count by priority
            const priorityCounts = await Memo.aggregate([
                { $match: memoFilter },
                {
                    $group: {
                        _id: '$priority',
                        count: { $sum: 1 }
                    }
                },
                { $sort: { count: -1 } }
            ]);

            console.log('\n📋 Memos by Priority:');
            priorityCounts.forEach(item => {
                console.log(`   ${item._id || 'N/A'}: ${item.count}`);
            });

            // Count by activity type
            const activityTypeCounts = await Memo.aggregate([
                { $match: memoFilter },
                {
                    $group: {
                        _id: '$activityType',
                        count: { $sum: 1 }
                    }
                },
                { $sort: { count: -1 } }
            ]);

            console.log('\n📋 Memos by Activity Type:');
            activityTypeCounts.forEach(item => {
                console.log(`   ${item._id || 'N/A'}: ${item.count}`);
            });

            // Recent memos
            const recentMemos = await Memo.find(memoFilter)
                .sort({ createdAt: -1 })
                .limit(5)
                .select('subject status priority createdAt')
                .lean();

            console.log('\n📄 Recent 5 Memos:');
            recentMemos.forEach((memo, idx) => {
                const date = memo.createdAt ? new Date(memo.createdAt).toLocaleString('en-US', { timeZone: 'Asia/Manila' }) : 'N/A';
                console.log(`   ${idx + 1}. ${memo.subject || 'N/A'}`);
                console.log(`      Status: ${memo.status || 'N/A'}, Priority: ${memo.priority || 'N/A'}`);
                console.log(`      Created: ${date}`);
            });
        }

        await mongoose.connection.close();
        console.log('\n✅ Database connection closed');
        process.exit(0);
    } catch (error) {
        console.error('❌ Error:', error.message);
        process.exit(1);
    }
}

countMemos();

