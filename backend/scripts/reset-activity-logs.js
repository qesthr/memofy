const mongoose = require('mongoose');
const dotenv = require('dotenv');

// Load environment variables
dotenv.config();

// Reuse existing DB connection helper
const connectDB = require('../config/db');
const ActivityLog = require('../models/ActivityLog');
const AuditLog = require('../models/AuditLog');

async function resetActivityLogs() {
    try {
        console.log('🚨 RESET ACTIVITY LOGS');
        console.log('════════════════════════════════════════════════════');
        console.log('This will DELETE ALL records from:');
        console.log('- ActivityLog collection');
        console.log('- AuditLog collection');
        console.log('════════════════════════════════════════════════════\n');

        // Safety confirmation for accidental runs (simple y/n prompt via env var)
        if (process.env.CONFIRM_RESET_LOGS !== 'true') {
            console.log('⚠️  Safety check: Set CONFIRM_RESET_LOGS=true in your .env before running this script.');
            console.log('    This prevents accidental deletion of all logs.');
            process.exit(1);
        }

        // Connect to the active MongoDB database
        await connectDB();

        // Delete all documents from both collections
        const [activityResult, auditResult] = await Promise.all([
            ActivityLog.deleteMany({}),
            AuditLog.deleteMany({})
        ]);

        console.log(`🗑️  ActivityLog: deleted ${activityResult.deletedCount} documents`);
        console.log(`🗑️  AuditLog   : deleted ${auditResult.deletedCount} documents`);

        console.log('\n✅ All activity logs have been reset successfully.');
        console.log('════════════════════════════════════════════════════');

        await mongoose.connection.close();
        process.exit(0);
    } catch (error) {
        console.error('❌ Error resetting activity logs:', error.message);
        try {
            await mongoose.connection.close();
        } catch (_) { /* ignore */ }
        process.exit(1);
    }
}

resetActivityLogs();


