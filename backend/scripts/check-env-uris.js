require('dotenv').config();

console.log('🔍 Checking MongoDB URIs in .env file...\n');
console.log('═'.repeat(60));

const uriVars = [
    'MONGODB_URI',
    'MONGODB_URI_PRIMARY',
    'MONGODB_URI_SECONDARY',
    'MONGODB_URI_OLD',
    'MONGODB_URI_NEW',
    'MONGODB_URI_SINGAPORE'
];

let found = 0;
uriVars.forEach(varName => {
    const value = process.env[varName];
    if (value) {
        found++;
        const masked = value.replace(/:[^:@]+@/, ':****@');
        console.log(`✅ ${varName.padEnd(25)} ${masked.substring(0, 50)}...`);
    }
});

console.log('═'.repeat(60));
console.log(`\nFound ${found} MongoDB URI variable(s)`);

if (found === 0) {
    console.log('\n❌ No MongoDB URIs found in .env file!');
} else if (found === 1) {
    console.log('\n⚠️  Only one MongoDB URI found. Need two for migration:');
    console.log('   - One for old/source database');
    console.log('   - One for new/target database');
    console.log('\n💡 Add one of these to your .env:');
    console.log('   MONGODB_URI_SECONDARY=mongodb+srv://...');
    console.log('   MONGODB_URI_NEW=mongodb+srv://...');
    console.log('   MONGODB_URI_SINGAPORE=mongodb+srv://...');
} else {
    console.log('\n✅ Multiple URIs found! Ready for migration.');
    console.log('\n💡 To migrate, run: npm run mongodb:migrate');
}

