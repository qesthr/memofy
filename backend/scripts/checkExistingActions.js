/**
 * Check existing action types in database
 */
require('dotenv').config();
const mongoose = require('mongoose');
const path = require('path');
const connectDB = require(path.join(__dirname, '../config/db'));
const AuditLog = require(path.join(__dirname, '../models/AuditLog'));
const Memo = require(path.join(__dirname, '../models/Memo'));

async function checkActions() {
    try {
        await connectDB();
        console.log('✅ Connected to MongoDB\n');

        // Get AuditLog actions
        const auditActions = await AuditLog.distinct('action');
        console.log('📋 AuditLog Actions:', auditActions.sort());
        console.log(`   Total unique actions: ${auditActions.length}\n`);

        // Get Memo activityTypes
        const memoActivityTypes = await Memo.distinct('activityType', { activityType: { $ne: null } });
        console.log('📋 Memo ActivityTypes:', memoActivityTypes.sort());
        console.log(`   Total unique types: ${memoActivityTypes.length}\n`);

        // Get counts for each
        console.log('📊 AuditLog Action Counts:');
        const auditCounts = await AuditLog.aggregate([
            { $group: { _id: '$action', count: { $sum: 1 } } },
            { $sort: { count: -1 } }
        ]);
        auditCounts.forEach(item => {
            console.log(`   ${item._id}: ${item.count}`);
        });

        console.log('\n📊 Memo ActivityType Counts:');
        const memoCounts = await Memo.aggregate([
            { $match: { activityType: { $ne: null } } },
            { $group: { _id: '$activityType', count: { $sum: 1 } } },
            { $sort: { count: -1 } }
        ]);
        memoCounts.forEach(item => {
            console.log(`   ${item._id}: ${item.count}`);
        });

        await mongoose.connection.close();
    } catch (error) {
        console.error('Error:', error);
        process.exit(1);
    }
}

checkActions();

