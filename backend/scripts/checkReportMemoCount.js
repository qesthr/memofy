/**
 * Script to check how Report counts memos vs actual count
 * Run with: node backend/scripts/checkReportMemoCount.js
 */

require('dotenv').config();
const mongoose = require('mongoose');
const connectDB = require('../config/db');
const Memo = require('../models/Memo');
const User = require('../models/User');

async function checkReportMemoCount() {
    try {
        await connectDB();
        console.log('✅ Connected to MongoDB\n');

        // Get admin and secretary user IDs (same as reportService does)
        const adminSecretaryUsers = await User.find({
            role: { $in: ['admin', 'secretary'] }
        }).select('_id role email').lean();

        const adminSecretaryIds = adminSecretaryUsers.map(u => u._id);
        console.log(`👥 Admin/Secretary Users: ${adminSecretaryUsers.length}`);
        adminSecretaryUsers.forEach(u => {
            console.log(`   - ${u.email} (${u.role})`);
        });
        console.log('');

        // System activity types that Report excludes
        const systemActivityTypes = [
            'user_activity',
            'system_notification',
            'user_deleted',
            'password_reset',
            'welcome_email'
        ];

        // Report filter (same as reportService.getMemoFilter)
        const reportFilter = {
            sender: { $in: adminSecretaryIds },
            activityType: { $nin: systemActivityTypes },
            status: { $ne: 'deleted' }
        };

        // Count using Report filter
        const reportCount = await Memo.countDocuments(reportFilter);
        console.log(`📊 Report Filter Count: ${reportCount}`);
        console.log(`   Filter: sender in [admin/secretary], activityType NOT IN ${systemActivityTypes.join(', ')}, status != deleted\n`);

        // Breakdown by activityType for memos sent by admin/secretary
        console.log('📋 Breakdown of Memos Sent by Admin/Secretary:');
        console.log('='.repeat(80));

        const adminSecretaryMemos = await Memo.aggregate([
            {
                $match: {
                    sender: { $in: adminSecretaryIds },
                    status: { $ne: 'deleted' }
                }
            },
            {
                $group: {
                    _id: '$activityType',
                    count: { $sum: 1 }
                }
            },
            {
                $sort: { count: -1 }
            }
        ]);

        let includedInReport = 0;
        let excludedFromReport = 0;

        adminSecretaryMemos.forEach(item => {
            const activityType = item._id === null ? '(null - actual memos)' : item._id;
            const count = item.count;
            const isExcluded = item._id !== null && systemActivityTypes.includes(item._id);

            if (isExcluded) {
                excludedFromReport += count;
            } else {
                includedInReport += count;
            }

            const status = isExcluded ? '❌ EXCLUDED' : '✅ INCLUDED';
            console.log(`  ${activityType}: ${count} memos ${status}`);
        });

        console.log('\n' + '='.repeat(80));
        console.log(`\n📈 Summary:`);
        console.log(`    Included in Report: ${includedInReport}`);
        console.log(`    Excluded from Report: ${excludedFromReport}`);
        console.log(`    Report Count: ${reportCount}`);
        console.log(`    Expected: ${includedInReport}`);

        // Check for user_profile_edited (should be excluded but isn't in the filter!)
        const userProfileEditedCount = await Memo.countDocuments({
            sender: { $in: adminSecretaryIds },
            activityType: 'user_profile_edited',
            status: { $ne: 'deleted' }
        });

        console.log(`\n⚠️  Issue Found:`);
        console.log(`    user_profile_edited memos: ${userProfileEditedCount}`);
        console.log(`    These are INCLUDED in Report but should be EXCLUDED!`);

        // Check actual memos (activityType = null) sent by admin/secretary
        const actualMemosCount = await Memo.countDocuments({
            sender: { $in: adminSecretaryIds },
            activityType: null,
            status: { $ne: 'deleted' }
        });

        console.log(`\n📄 Actual Memos (activityType = null) sent by Admin/Secretary: ${actualMemosCount}`);

        // Check if there are memos with activityType = null but sent by faculty
        const facultyMemosCount = await Memo.countDocuments({
            activityType: null,
            status: { $ne: 'deleted' }
        });

        const facultyUsers = await User.find({ role: 'faculty' }).select('_id').lean();
        const facultyIds = facultyUsers.map(u => u._id);

        const actualMemosByFaculty = await Memo.countDocuments({
            sender: { $in: facultyIds },
            activityType: null,
            status: { $ne: 'deleted' }
        });

        console.log(`\n📄 Total Actual Memos (all roles): ${facultyMemosCount}`);
        console.log(`    Sent by Admin/Secretary: ${actualMemosCount}`);
        console.log(`    Sent by Faculty: ${actualMemosByFaculty}`);

        // Check for system_notification memos sent by admin/secretary
        const systemNotificationCount = await Memo.countDocuments({
            sender: { $in: adminSecretaryIds },
            activityType: 'system_notification',
            status: { $ne: 'deleted' }
        });

        console.log(`\n📄 System Notification Memos sent by Admin/Secretary: ${systemNotificationCount}`);
        console.log(`    These are EXCLUDED from Report (correct)`);

        // Final calculation
        const correctReportCount = actualMemosCount + userProfileEditedCount;
        console.log(`\n💡 Calculation:`);
        console.log(`    Actual Memos (admin/secretary): ${actualMemosCount}`);
        console.log(`    user_profile_edited (admin/secretary): ${userProfileEditedCount}`);
        console.log(`    Total (what Report shows): ${correctReportCount}`);
        console.log(`    Report Filter Count: ${reportCount}`);

        if (correctReportCount !== reportCount) {
            console.log(`\n❌ Mismatch! Report count (${reportCount}) doesn't match calculation (${correctReportCount})`);
        } else {
            console.log(`\n✅ Match! Report count matches calculation`);
        }

        console.log('\n✅ Analysis complete!\n');

    } catch (error) {
        console.error('❌ Error:', error);
    } finally {
        await mongoose.connection.close();
        console.log('🔌 Database connection closed');
    }
}

checkReportMemoCount();

