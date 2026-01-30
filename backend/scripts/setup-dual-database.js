const fs = require('fs');
const path = require('path');
require('dotenv').config();

/**
 * Setup script to configure both old and new databases for easy switching
 */

const envPath = path.resolve(__dirname, '../../.env');

// Old database (Hong Kong)
const OLD_URI = 'mongodb+srv://memofy_db:memofydb=@cluster0.ailayze.mongodb.net/memofy?retryWrites=true&w=majority&appName=Cluster0';

// New database (Singapore)
const NEW_URI = 'mongodb+srv://2301107552_db_user:Joenil20@memofy.y3m50tg.mongodb.net/memofy?appName=Memofy';

function setupDualDatabase() {
    try {
        const envContent = fs.readFileSync(envPath, 'utf8');
        const lines = envContent.split('\n');

        let hasPrimary = false;
        let hasSecondary = false;
        let hasActive = false;
        let mongodbUriIndex = -1;

        // Check what's already there
        lines.forEach((line, index) => {
            if (line.trim().startsWith('MONGODB_URI_PRIMARY=')) {hasPrimary = true;}
            if (line.trim().startsWith('MONGODB_URI_SECONDARY=')) {hasSecondary = true;}
            if (line.trim().startsWith('MONGODB_ACTIVE=')) {hasActive = true;}
            if (line.trim().startsWith('MONGODB_URI=') && !line.includes('PRIMARY') && !line.includes('SECONDARY')) {
                mongodbUriIndex = index;
            }
        });

        const newLines = [...lines];
        const changes = [];

        // Add or update PRIMARY (old database)
        if (!hasPrimary) {
            // Find where to insert (after MONGODB_URI or at end)
            const insertIndex = mongodbUriIndex >= 0 ? mongodbUriIndex + 1 : newLines.length;
            newLines.splice(insertIndex, 0, `MONGODB_URI_PRIMARY=${OLD_URI}`);
            changes.push('Added MONGODB_URI_PRIMARY (old database - Hong Kong)');
        } else {
            // Update existing
            for (let i = 0; i < newLines.length; i++) {
                if (newLines[i].trim().startsWith('MONGODB_URI_PRIMARY=')) {
                    newLines[i] = `MONGODB_URI_PRIMARY=${OLD_URI}`;
                    changes.push('Updated MONGODB_URI_PRIMARY');
                    break;
                }
            }
        }

        // Add or update SECONDARY (new database)
        if (!hasSecondary) {
            // Find where to insert (after PRIMARY or MONGODB_URI)
            let insertIndex = -1;
            for (let i = 0; i < newLines.length; i++) {
                if (newLines[i].includes('MONGODB_URI_PRIMARY=')) {
                    insertIndex = i + 1;
                    break;
                }
            }
            if (insertIndex === -1) {
                insertIndex = mongodbUriIndex >= 0 ? mongodbUriIndex + 1 : newLines.length;
            }
            newLines.splice(insertIndex, 0, `MONGODB_URI_SECONDARY=${NEW_URI}`);
            changes.push('Added MONGODB_URI_SECONDARY (new database - Singapore)');
        } else {
            // Update existing
            for (let i = 0; i < newLines.length; i++) {
                if (newLines[i].trim().startsWith('MONGODB_URI_SECONDARY=')) {
                    newLines[i] = `MONGODB_URI_SECONDARY=${NEW_URI}`;
                    changes.push('Updated MONGODB_URI_SECONDARY');
                    break;
                }
            }
        }

        // Add MONGODB_ACTIVE if not present (default to primary)
        if (!hasActive) {
            let insertIndex = -1;
            for (let i = 0; i < newLines.length; i++) {
                if (newLines[i].includes('MONGODB_URI_SECONDARY=')) {
                    insertIndex = i + 1;
                    break;
                }
            }
            if (insertIndex >= 0) {
                newLines.splice(insertIndex, 0, '');
                newLines.splice(insertIndex + 1, 0, '# Which database to use: primary (old) or secondary (new)');
                newLines.splice(insertIndex + 2, 0, 'MONGODB_ACTIVE=primary');
                changes.push('Added MONGODB_ACTIVE (default: primary)');
            }
        }

        // Write updated file
        fs.writeFileSync(envPath, newLines.join('\n'), 'utf8');

        console.log('✅ Dual Database Setup Complete!');
        console.log('═'.repeat(60));
        console.log('\n📝 Configuration:');
        console.log('   PRIMARY (Old - Hong Kong):');
        console.log('   ', OLD_URI.replace(/:[^:@]+@/, ':****@'));
        console.log('\n   SECONDARY (New - Singapore):');
        console.log('   ', NEW_URI.replace(/:[^:@]+@/, ':****@'));
        console.log('\n📋 Changes made:');
        changes.forEach(change => console.log('   ✅', change));

        console.log('\n💡 How to Switch:');
        console.log('   Switch to PRIMARY (old):  npm run mongodb:switch primary');
        console.log('   Switch to SECONDARY (new): npm run mongodb:switch secondary');
        console.log('   Check current status:      npm run mongodb:status');
        console.log('\n⚠️  Remember to restart your application after switching!');

        return true;
    } catch (error) {
        console.error('❌ Error setting up dual database:', error.message);
        return false;
    }
}

setupDualDatabase();

