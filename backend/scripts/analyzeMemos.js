/**
 * Script to analyze memos collection and understand the discrepancy
 * Run with: node backend/scripts/analyzeMemos.js
 */

require('dotenv').config();
const mongoose = require('mongoose');
const connectDB = require('../config/db');
const Memo = require('../models/Memo');

async function analyzeMemos() {
    try {
        // Connect to database
        await connectDB();
        console.log('✅ Connected to MongoDB\n');

        const db = mongoose.connection.db;
        const totalCount = await Memo.countDocuments();
        console.log(`📊 Total Memos in Collection: ${totalCount}\n`);

        // Analyze by activityType
        console.log('📋 Breakdown by activityType:');
        console.log('='.repeat(80));

        const activityTypeBreakdown = await Memo.aggregate([
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

        let systemMemoCount = 0;
        let actualMemoCount = 0;

        activityTypeBreakdown.forEach(item => {
            const activityType = item._id === null ? '(null - actual memos)' : item._id;
            const count = item.count;

            // System memos have activityType set
            if (item._id !== null) {
                systemMemoCount += count;
            } else {
                actualMemoCount += count;
            }

            console.log(`  ${activityType}: ${count} memos`);
        });

        console.log('\n' + '='.repeat(80));
        console.log(`\n📈 Summary:`);
        console.log(`    Actual Memos (activityType = null): ${actualMemoCount}`);
        console.log(`    System Memos (activityType != null): ${systemMemoCount}`);
        console.log(`    Total: ${totalCount}`);
        console.log(`    Expected in Reports: ${actualMemoCount} (actual memos only)`);

        // Check status distribution for actual memos
        console.log('\n📋 Status Distribution (Actual Memos Only):');
        console.log('='.repeat(80));

        const statusBreakdown = await Memo.aggregate([
            {
                $match: { activityType: null }
            },
            {
                $group: {
                    _id: '$status',
                    count: { $sum: 1 }
                }
            },
            {
                $sort: { count: -1 }
            }
        ]);

        statusBreakdown.forEach(item => {
            console.log(`  ${item._id || '(null)'}: ${item.count} memos`);
        });

        // Check by folder
        console.log('\n📋 Folder Distribution (Actual Memos Only):');
        console.log('='.repeat(80));

        const folderBreakdown = await Memo.aggregate([
            {
                $match: { activityType: null }
            },
            {
                $group: {
                    _id: '$folder',
                    count: { $sum: 1 }
                }
            },
            {
                $sort: { count: -1 }
            }
        ]);

        folderBreakdown.forEach(item => {
            console.log(`  ${item._id || '(null)'}: ${item.count} memos`);
        });

        // Check memo creation over time
        console.log('\n📅 Memo Creation Timeline (Actual Memos Only):');
        console.log('='.repeat(80));

        const timeline = await Memo.aggregate([
            {
                $match: { activityType: null }
            },
            {
                $group: {
                    _id: {
                        year: { $year: '$createdAt' },
                        month: { $month: '$createdAt' }
                    },
                    count: { $sum: 1 }
                }
            },
            {
                $sort: { '_id.year': -1, '_id.month': -1 }
            },
            {
                $limit: 12
            }
        ]);

        timeline.forEach(item => {
            const monthName = new Date(item._id.year, item._id.month - 1).toLocaleString('default', { month: 'long', year: 'numeric' });
            console.log(`  ${monthName}: ${item.count} memos`);
        });

        // Check system memo types that should NOT be in reports
        console.log('\n📋 System Memo Types (Should NOT appear in Reports):');
        console.log('='.repeat(80));

        const systemMemoTypes = await Memo.aggregate([
            {
                $match: { activityType: { $ne: null } }
            },
            {
                $group: {
                    _id: '$activityType',
                    count: { $sum: 1 },
                    sampleSubjects: { $push: '$subject' }
                }
            },
            {
                $sort: { count: -1 }
            }
        ]);

        systemMemoTypes.forEach(item => {
            console.log(`\n  ${item._id}: ${item.count} memos`);
            if (item.sampleSubjects && item.sampleSubjects.length > 0) {
                const uniqueSubjects = [...new Set(item.sampleSubjects.slice(0, 3))];
                uniqueSubjects.forEach(subject => {
                    console.log(`    Example: "${subject?.substring(0, 60) || 'N/A'}${subject?.length > 60 ? '...' : ''}"`);
                });
            }
        });

        // Check if there are memos with both activityType and regular memo status
        console.log('\n🔍 Checking for Edge Cases:');
        console.log('='.repeat(80));

        const edgeCases = await Memo.aggregate([
            {
                $match: {
                    activityType: null,
                    status: { $in: ['deleted', 'archived'] }
                }
            },
            {
                $group: {
                    _id: '$status',
                    count: { $sum: 1 }
                }
            }
        ]);

        console.log(`  Deleted/Archived Actual Memos: ${edgeCases.reduce((sum, item) => sum + item.count, 0)}`);

        // Check for memos that might be counted differently
        const sentMemos = await Memo.countDocuments({
            activityType: null,
            status: 'sent'
        });

        const pendingMemos = await Memo.countDocuments({
            activityType: null,
            status: 'pending'
        });

        const approvedMemos = await Memo.countDocuments({
            activityType: null,
            status: 'approved'
        });

        console.log(`  Sent Memos: ${sentMemos}`);
        console.log(`  Pending Memos: ${pendingMemos}`);
        console.log(`  Approved Memos: ${approvedMemos}`);

        // Sample actual memos
        console.log('\n📄 Sample Actual Memos (First 5):');
        console.log('='.repeat(80));

        const sampleMemos = await Memo.find({ activityType: null })
            .sort({ createdAt: -1 })
            .limit(5)
            .populate('sender', 'firstName lastName email role')
            .populate('recipient', 'firstName lastName email')
            .lean();

        sampleMemos.forEach((memo, index) => {
            console.log(`\n[${index + 1}] Memo ID: ${memo._id}`);
            console.log(`    Subject: ${memo.subject || 'N/A'}`);
            console.log(`    Status: ${memo.status || 'N/A'}`);
            console.log(`    Folder: ${memo.folder || 'N/A'}`);
            console.log(`    Sender: ${memo.sender ? `${memo.sender.firstName || ''} ${memo.sender.lastName || ''}`.trim() || memo.sender.email : 'N/A'}`);
            console.log(`    Recipient: ${memo.recipient ? `${memo.recipient.firstName || ''} ${memo.recipient.lastName || ''}`.trim() || memo.recipient.email : 'N/A'}`);
            console.log(`    Created: ${memo.createdAt || 'N/A'}`);
            console.log('-'.repeat(80));
        });

        console.log('\n✅ Analysis complete!\n');
        console.log('💡 Key Insight:');
        console.log(`   Reports should count only actual memos (activityType = null): ${actualMemoCount}`);
        console.log(`   System memos (activityType != null) are activity logs, not real memos: ${systemMemoCount}`);

    } catch (error) {
        console.error('❌ Error analyzing memos:', error);
    } finally {
        await mongoose.connection.close();
        console.log('🔌 Database connection closed');
    }
}

// Run the analysis
analyzeMemos();

