const fs = require('fs');
const path = require('path');
require('dotenv').config();

/**
 * Script to easily switch between MongoDB databases
 * Usage: node backend/scripts/switch-mongodb.js [primary|secondary]
 */

const envPath = path.resolve(__dirname, '../../.env');

function readEnvFile() {
    try {
        return fs.readFileSync(envPath, 'utf8');
    } catch (error) {
        console.error('❌ Could not read .env file:', error.message);
        process.exit(1);
    }
}

function writeEnvFile(content) {
    try {
        fs.writeFileSync(envPath, content, 'utf8');
        console.log('✅ .env file updated successfully!');
    } catch (error) {
        console.error('❌ Could not write .env file:', error.message);
        process.exit(1);
    }
}

function switchDatabase(target) {
    const envContent = readEnvFile();
    const lines = envContent.split('\n');

    let foundMongoActive = false;
    let updated = false;

    const newLines = lines.map(line => {
        // Update MONGODB_ACTIVE
        if (line.trim().startsWith('MONGODB_ACTIVE=')) {
            foundMongoActive = true;
            updated = true;
            return `MONGODB_ACTIVE=${target}`;
        }
        return line;
    });

    // If MONGODB_ACTIVE doesn't exist, add it
    if (!foundMongoActive) {
        // Find where to insert (after other MONGODB_URI lines)
        let insertIndex = -1;
        for (let i = 0; i < newLines.length; i++) {
            if (newLines[i].includes('MONGODB_URI')) {
                insertIndex = i + 1;
            }
        }

        if (insertIndex >= 0) {
            newLines.splice(insertIndex, 0, `MONGODB_ACTIVE=${target}`);
        } else {
            newLines.push(`MONGODB_ACTIVE=${target}`);
        }
        updated = true;
    }

    if (updated) {
        writeEnvFile(newLines.join('\n'));
    } else {
        console.log('⚠️  No changes needed');
    }
}

function showCurrentStatus() {
    const envContent = readEnvFile();
    const lines = envContent.split('\n');

    let active = 'not set';
    let primary = false;
    let secondary = false;

    lines.forEach(line => {
        if (line.trim().startsWith('MONGODB_ACTIVE=')) {
            active = line.split('=')[1].trim();
        }
        if (line.trim().startsWith('MONGODB_URI_PRIMARY=') || line.trim().startsWith('MONGODB_URI=')) {
            primary = true;
        }
        if (line.trim().startsWith('MONGODB_URI_SECONDARY=')) {
            secondary = true;
        }
    });

    console.log('\n📊 Current MongoDB Configuration:');
    console.log('═'.repeat(60));
    console.log(`Active Database: ${active === 'primary' ? '✅ PRIMARY' : active === 'secondary' ? '✅ SECONDARY' : '⚠️  Not set (using default)'}`);
    console.log(`Primary URI: ${primary ? '✅ Set' : '❌ Not set'}`);
    console.log(`Secondary URI: ${secondary ? '✅ Set' : '❌ Not set'}`);
    console.log('═'.repeat(60));
    console.log('\n💡 Usage:');
    console.log('   node backend/scripts/switch-mongodb.js primary   - Switch to primary');
    console.log('   node backend/scripts/switch-mongodb.js secondary - Switch to secondary');
    console.log('   node backend/scripts/switch-mongodb.js status    - Show current status');
}

// Main execution
const target = process.argv[2];

if (!target || target === 'status') {
    showCurrentStatus();
} else if (target === 'primary' || target === 'secondary') {
    console.log(`\n🔄 Switching to ${target.toUpperCase()} database...`);
    switchDatabase(target);
    console.log(`\n✅ Switched to ${target.toUpperCase()}!`);
    console.log('⚠️  Remember to restart your application for changes to take effect.');
} else {
    console.error('❌ Invalid option. Use: primary, secondary, or status');
    process.exit(1);
}

