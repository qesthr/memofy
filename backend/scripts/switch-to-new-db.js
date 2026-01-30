const fs = require('fs');
const path = require('path');
require('dotenv').config();

/**
 * Script to switch from old database to new database
 */

const envPath = path.resolve(__dirname, '../../.env');

// Old database (Hong Kong)
const OLD_URI = 'mongodb+srv://memofy_db:memofydb=@cluster0.ailayze.mongodb.net/memofy?retryWrites=true&w=majority&appName=Cluster0';

// New database (Singapore)
const NEW_URI = 'mongodb+srv://2301107552_db_user:Joenil20@memofy.y3m50tg.mongodb.net/memofy?appName=Memofy';

function updateEnvFile() {
    try {
        const envContent = fs.readFileSync(envPath, 'utf8');
        const lines = envContent.split('\n');

        let updated = false;
        const newLines = lines.map(line => {
            // Update MONGODB_URI to new database
            if (line.trim().startsWith('MONGODB_URI=') && line.includes('ailayze')) {
                updated = true;
                return `MONGODB_URI=${NEW_URI}`;
            }
            return line;
        });

        // Also ensure we have PRIMARY and SECONDARY set for easy switching
        let hasPrimary = false;
        let hasSecondary = false;

        newLines.forEach(line => {
            if (line.trim().startsWith('MONGODB_URI_PRIMARY=')) {hasPrimary = true;}
            if (line.trim().startsWith('MONGODB_URI_SECONDARY=')) {hasSecondary = true;}
        });

        // Add PRIMARY and SECONDARY if not present
        if (!hasPrimary || !hasSecondary) {
            // Find where to insert (after MONGODB_URI)
            let insertIndex = -1;
            for (let i = 0; i < newLines.length; i++) {
                if (newLines[i].includes('MONGODB_URI=') && !newLines[i].includes('PRIMARY') && !newLines[i].includes('SECONDARY')) {
                    insertIndex = i + 1;
                    break;
                }
            }

            if (insertIndex >= 0) {
                const toInsert = [];
                if (!hasPrimary) {
                    toInsert.push(`MONGODB_URI_PRIMARY=${OLD_URI}`);
                }
                if (!hasSecondary) {
                    toInsert.push(`MONGODB_URI_SECONDARY=${NEW_URI}`);
                }
                newLines.splice(insertIndex, 0, ...toInsert);
            }
        }

        if (updated || !hasPrimary || !hasSecondary) {
            fs.writeFileSync(envPath, newLines.join('\n'), 'utf8');
            console.log('✅ .env file updated successfully!');
            return true;
        } else {
            console.log('ℹ️  .env file already configured correctly');
            return false;
        }
    } catch (error) {
        console.error('❌ Error updating .env file:', error.message);
        return false;
    }
}

console.log('🔄 Switching to New Database (Singapore)');
console.log('═'.repeat(60));
console.log('Old (Hong Kong):', OLD_URI.replace(/:[^:@]+@/, ':****@'));
console.log('New (Singapore):', NEW_URI.replace(/:[^:@]+@/, ':****@'));
console.log('═'.repeat(60));

const updated = updateEnvFile();

if (updated) {
    console.log('\n✅ Configuration updated!');
    console.log('\n📝 Changes made:');
    console.log('   - MONGODB_URI → Updated to new database');
    console.log('   - MONGODB_URI_PRIMARY → Set to old database (for backup)');
    console.log('   - MONGODB_URI_SECONDARY → Set to new database');
    console.log('\n⚠️  IMPORTANT: Restart your application for changes to take effect!');
    console.log('   Run: npm start (or stop and restart your server)');
} else {
    console.log('\n✅ Already configured correctly!');
    console.log('💡 If you need to switch back, use: npm run mongodb:switch primary');
}

