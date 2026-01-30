const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

const Memo = require('../models/Memo');

async function testActivityLogs() {
    try {
        const mongoUri = 'mongodb+srv://memofy_db:memofydb=@cluster0.ailayze.mongodb.net/memofy?retryWrites=true&w=majority&appName=Cluster0';

        console.log(`🔗 Connecting to MongoDB Atlas...`);
        await mongoose.connect(mongoUri);

        const dbName = mongoose.connection.db.databaseName;
        console.log(`✅ Connected to MongoDB`);
        console.log(`📦 Database: ${dbName}\n`);

        const now = new Date();
        const startDate = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const endDate = new Date();

        console.log(`📅 Date Range: ${startDate.toISOString().split('T')[0]} to ${endDate.toISOString().split('T')[0]}\n`);

        const systemActivityTypes = [
            'user_activity',
            'system_notification',
            'user_deleted',
            'password_reset',
            'welcome_email',
            'memo_sent',
            'memo_approved',
            'memo_rejected',
            'pending_memo'
        ];

        const dateFilter = {
            createdAt: {
                $gte: startDate,
                $lte: endDate
            },
            status: { $ne: 'deleted' },
            activityType: { $in: systemActivityTypes }
        };

        const stats = await Memo.aggregate([
            { $match: dateFilter },
            {
                $group: {
                    _id: {
                        $dateToString: { format: '%Y-%m-%d', date: '$createdAt' }
                    },
                    count: { $sum: 1 }
                }
            },
            { $sort: { _id: 1 } }
        ]);

        console.log('📊 Sample Activity Logs Data (first 10):');
        stats.slice(0, 10).forEach(item => {
            console.log(`   ${item._id}: ${item.count} activities`);
        });

        console.log(`\n✅ Total dates with activity: ${stats.length}`);
        console.log(`✅ Total activities: ${stats.reduce((sum, item) => sum + item.count, 0)}`);

        await mongoose.connection.close();
        console.log('\n✅ Database connection closed');
        process.exit(0);
    } catch (error) {
        console.error('❌ Error:', error.message);
        process.exit(1);
    }
}

testActivityLogs();

