require('dotenv').config();
const mongoose = require('mongoose');
const AuditLog = require('../models/AuditLog');

async function checkAuditLogs() {
    try {
        await mongoose.connect(process.env.MONGODB_URI);
        console.log('✅ Connected to MongoDB\n');

        const count = await AuditLog.countDocuments();
        console.log(`📊 Total Audit Logs: ${count}\n`);

        if (count === 0) {
            console.log('No audit logs found yet.');
            console.log('Note: Audit logs are created when users login/logout or perform actions.');
        } else {
            console.log('=== Recent Audit Logs ===\n');
            const logs = await AuditLog.find({})
                .sort({ createdAt: -1 })
                .limit(20)
                .lean();

            logs.forEach((log, idx) => {
                console.log(`${idx + 1}. ${log.subject || 'User Activity'}`);
                console.log(`   Email: ${log.email || 'N/A'}`);
                console.log(`   Action: ${log.action || 'N/A'}`);
                console.log(`   Message: ${log.message || '(no message)'}`);
                console.log(`   Date: ${log.createdAt ? new Date(log.createdAt).toLocaleString() : 'N/A'}`);
                console.log(`   Read: ${log.isRead ? 'Yes' : 'No'}`);
                if (log.metadata && Object.keys(log.metadata).length > 0) {
                    console.log(`   Metadata: ${JSON.stringify(log.metadata)}`);
                }
                console.log('');
            });
        }

        await mongoose.disconnect();
        process.exit(0);
    } catch (error) {
        console.error('❌ Error:', error.message);
        process.exit(1);
    }
}

checkAuditLogs();

