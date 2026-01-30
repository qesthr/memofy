const mongoose = require('mongoose');
require('dotenv').config({ path: require('path').join(__dirname, '../.env') });

const Memo = require('../models/Memo');

async function showSystemMemoBreakdown() {
    try {
        const mongoUri = 'mongodb+srv://memofy_db:memofydb=@cluster0.ailayze.mongodb.net/memofy?retryWrites=true&w=majority&appName=Cluster0';

        console.log(`🔗 Connecting to MongoDB Atlas...`);
        await mongoose.connect(mongoUri);

        const dbName = mongoose.connection.db.databaseName;
        console.log(`✅ Connected to MongoDB`);
        console.log(`📦 Database: ${dbName}\n`);

        const systemActivityTypes = [
            'user_activity',
            'system_notification',
            'user_deleted',
            'password_reset',
            'welcome_email'
        ];

        console.log('📊 System Memo Breakdown:\n');

        const counts = await Promise.all(
            systemActivityTypes.map(type =>
                Memo.countDocuments({
                    activityType: type,
                    status: { $ne: 'deleted' }
                })
            )
        );

        systemActivityTypes.forEach((type, i) => {
            console.log(`   ${type}: ${counts[i]}`);
        });

        const totalSystem = counts.reduce((sum, count) => sum + count, 0);
        console.log(`\n   Total System Memos: ${totalSystem}`);

        // Also check for memos with null activityType (should be regular memos)
        const nullActivityType = await Memo.countDocuments({
            activityType: null,
            status: { $ne: 'deleted' }
        });
        console.log(`   Memos with null activityType: ${nullActivityType}`);

        // And check for other activity types
        const otherActivityTypes = await Memo.aggregate([
            {
                $match: {
                    activityType: { $nin: [...systemActivityTypes, null] },
                    status: { $ne: 'deleted' }
                }
            },
            {
                $group: {
                    _id: '$activityType',
                    count: { $sum: 1 }
                }
            },
            { $sort: { count: -1 } }
        ]);

        if (otherActivityTypes.length > 0) {
            console.log(`\n   Other Activity Types:`);
            otherActivityTypes.forEach(item => {
                console.log(`     ${item._id}: ${item.count}`);
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

showSystemMemoBreakdown();

