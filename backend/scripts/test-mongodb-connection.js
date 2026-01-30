const mongoose = require('mongoose');
require('dotenv').config();

async function testConnection() {
    try {
        console.log('🔌 Testing MongoDB connection...');
        console.log(`📍 Connection string: ${process.env.MONGODB_URI ? process.env.MONGODB_URI.replace(/:[^:@]+@/, ':****@') : 'NOT SET'}\n`);

        // Connect with timeout
        const startTime = Date.now();
        await mongoose.connect(process.env.MONGODB_URI, {
            serverSelectionTimeoutMS: 10000, // 10 second timeout
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        const connectionTime = Date.now() - startTime;

        console.log('✅ MongoDB connection successful!');
        console.log(`⏱️  Connection time: ${connectionTime}ms`);
        console.log(`📊 Database: ${mongoose.connection.db.databaseName}`);
        console.log(`🌐 Host: ${mongoose.connection.host}`);
        console.log(`🔌 Ready state: ${mongoose.connection.readyState === 1 ? 'Connected' : 'Not Connected'}`);

        // Get region information from server description
        try {
            const topology = mongoose.connection.db.client.topology;
            if (topology && topology.s && topology.s.servers) {
                const servers = Array.from(topology.s.servers.values());
                if (servers.length > 0) {
                    const server = servers[0];
                    const description = server.description;
                    if (description) {
                        console.log(`\n🌍 Region Information:`);
                        console.log(`   Host: ${description.host || 'N/A'}`);
                        console.log(`   Address: ${description.address || 'N/A'}`);
                        if (description.tags) {
                            console.log(`   Tags: ${JSON.stringify(description.tags)}`);
                        }
                        // Try to extract region from hostname (Atlas format: cluster0-shard-00-00.xxxxx.mongodb.net)
                        const hostname = description.host || description.address || '';
                        if (hostname.includes('.mongodb.net')) {
                            const parts = hostname.split('.');
                            if (parts.length >= 2) {
                                // Extract potential region identifier
                                const subdomain = parts[0];
                                console.log(`   Subdomain: ${subdomain}`);
                            }
                        }
                    }
                }
            }
        } catch (err) {
            console.log(`\n⚠️  Could not retrieve detailed region info: ${err.message}`);
        }

        // Try to get region from connection string
        const uri = process.env.MONGODB_URI || '';
        if (uri.includes('cluster0.ailayze')) {
            console.log(`\n📍 Cluster: cluster0.ailayze`);
            console.log(`   Note: To see exact region, check MongoDB Atlas dashboard`);
            console.log(`   Atlas URL: https://cloud.mongodb.com/`);
        }

        // Test a simple query
        const collections = await mongoose.connection.db.listCollections().toArray();
        console.log(`\n📁 Collections found: ${collections.length}`);
        collections.forEach(col => {
            console.log(`   - ${col.name}`);
        });

        await mongoose.disconnect();
        console.log('\n✅ Connection test completed successfully!');
        process.exit(0);
    } catch (error) {
        console.error('\n❌ MongoDB connection failed!');
        console.error(`Error: ${error.message}`);

        if (error.name === 'MongoServerSelectionError') {
            console.error('\n💡 Possible issues:');
            console.error('   - Network connectivity problem');
            console.error('   - MongoDB Atlas IP whitelist (check if your IP is allowed)');
            console.error('   - Incorrect connection string');
            console.error('   - MongoDB cluster is down');
        } else if (error.name === 'MongoAuthenticationError') {
            console.error('\n💡 Authentication failed:');
            console.error('   - Check username and password in connection string');
            console.error('   - Verify database user has proper permissions');
        }

        process.exit(1);
    }
}

testConnection();

