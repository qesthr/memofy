const mongoose = require('mongoose');

// Test different password encodings
const passwords = [
    '3c7LVflxbzh2VPzI=',      // Original
    '3c7LVflxbzh2VPzI%3D',    // URL encoded =
    encodeURIComponent('3c7LVflxbzh2VPzI=')  // Full URL encoding
];

const baseURI = 'mongodb+srv://2301107552_db_user:PASSWORD@memofy.y3m50tg.mongodb.net/memofy?appName=Memofy';

async function testConnection(uri, label) {
    try {
        console.log(`\n🔌 Testing: ${label}`);
        console.log(`   URI: ${uri.replace(/:[^:@]+@/, ':****@')}`);

        await mongoose.connect(uri, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const dbName = mongoose.connection.db.databaseName;
        const host = mongoose.connection.host;
        const collections = await mongoose.connection.db.listCollections().toArray();

        console.log(`   ✅ SUCCESS!`);
        console.log(`   Host: ${host}`);
        console.log(`   Database: ${dbName}`);
        console.log(`   Collections: ${collections.length}`);

        await mongoose.disconnect();
        return { success: true, uri };
    } catch (error) {
        console.log(`   ❌ Failed: ${error.message}`);
        try {
            await mongoose.disconnect();
        } catch (e) {}
        return { success: false, error: error.message };
    }
}

async function testAll() {
    console.log('🧪 Testing New Database Connection');
    console.log('═'.repeat(60));

    for (let i = 0; i < passwords.length; i++) {
        const uri = baseURI.replace('PASSWORD', passwords[i]);
        const label = i === 0 ? 'Original password' : i === 1 ? 'URL-encoded =' : 'Full URL encoding';
        const result = await testConnection(uri, label);

        if (result.success) {
            console.log(`\n✅ Working connection found!`);
            console.log(`\n💡 Use this URI in your .env file:`);
            console.log(`MONGODB_URI_SECONDARY=${result.uri}`);
            return result.uri;
        }
    }

    console.log(`\n❌ All connection attempts failed.`);
    console.log(`\n💡 Please check:`);
    console.log(`   1. Username is correct: 2301107552_db_user`);
    console.log(`   2. Password is correct: 3c7LVflxbzh2VPzI=`);
    console.log(`   3. Database user has proper permissions`);
    console.log(`   4. IP address is whitelisted in MongoDB Atlas`);

    return null;
}

testAll().then(() => process.exit(0)).catch(err => {
    console.error('Error:', err);
    process.exit(1);
});

