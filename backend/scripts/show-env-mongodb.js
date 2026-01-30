require('dotenv').config();

console.log('📄 MongoDB Configuration in .env file:');
console.log('═'.repeat(70));

const mongodbVars = [
    'MONGODB_URI',
    'MONGODB_URI_PRIMARY',
    'MONGODB_URI_SECONDARY',
    'MONGODB_URI_OLD',
    'MONGODB_URI_NEW',
    'MONGODB_URI_SINGAPORE',
    'MONGODB_ACTIVE'
];

let foundAny = false;

mongodbVars.forEach(varName => {
    const value = process.env[varName];
    if (value) {
        foundAny = true;
        // Mask password for security
        const masked = value.replace(/:[^:@]+@/, ':****@');
        const displayValue = masked.length > 70 ? masked.substring(0, 67) + '...' : masked;
        console.log(`${varName.padEnd(25)} = ${displayValue}`);
    }
});

if (!foundAny) {
    console.log('❌ No MongoDB variables found in .env file');
} else {
    console.log('═'.repeat(70));
    console.log('\n💡 Current Active Database:');
    const active = process.env.MONGODB_ACTIVE || 'primary (default)';
    console.log(`   ${active === 'primary' ? '✅ PRIMARY (Old - Hong Kong)' : active === 'secondary' ? '✅ SECONDARY (New - Singapore)' : '⚠️  ' + active}`);

    console.log('\n📋 What each variable does:');
    console.log('   MONGODB_URI_PRIMARY   → Old database (Hong Kong)');
    console.log('   MONGODB_URI_SECONDARY → New database (Singapore)');
    console.log('   MONGODB_ACTIVE        → Which one to use (primary/secondary)');
    console.log('   MONGODB_URI           → Fallback (used if PRIMARY/SECONDARY not set)');
}

console.log('\n');

