const mongoose = require('mongoose');
require('dotenv').config();

async function checkRegion() {
    try {
        console.log('🔍 Checking MongoDB Atlas Region...\n');

        const uri = process.env.MONGODB_URI;
        if (!uri) {
            console.error('❌ MONGODB_URI not found in environment variables');
            process.exit(1);
        }

        // Extract cluster name from connection string
        const clusterMatch = uri.match(/cluster[0-9]+\.([^.]+)\.mongodb\.net/);
        if (clusterMatch) {
            const clusterId = clusterMatch[0].split('.')[0];
            const regionId = clusterMatch[1];
            console.log(`📍 Cluster: ${clusterId}`);
            console.log(`📍 Region ID: ${regionId}`);
        }

        // Connect to MongoDB
        console.log('\n🔌 Connecting to MongoDB...');
        await mongoose.connect(uri, {
            serverSelectionTimeoutMS: 10000,
            socketTimeoutMS: 45000,
            connectTimeoutMS: 10000
        });

        console.log('✅ Connected successfully!\n');

        // Get server information
        const adminDb = mongoose.connection.db.admin();

        try {
            // Get server status
            const serverStatus = await adminDb.command({ serverStatus: 1 });
            console.log('📊 Server Information:');
            console.log(`   MongoDB Version: ${serverStatus.version || 'N/A'}`);
            console.log(`   Uptime: ${Math.floor((serverStatus.uptime || 0) / 60)} minutes`);
        } catch (err) {
            console.log('⚠️  Could not retrieve server status (may require admin privileges)');
        }

        // Get topology information
        const topology = mongoose.connection.db.client.topology;
        if (topology && topology.s && topology.s.servers) {
            const servers = Array.from(topology.s.servers.values());
            console.log(`\n🌍 Server Details:`);
            console.log(`   Number of servers: ${servers.length}`);

            servers.forEach((server, index) => {
                const desc = server.description;
                if (desc) {
                    console.log(`\n   Server ${index + 1}:`);
                    console.log(`      Host: ${desc.host || 'N/A'}`);
                    console.log(`      Address: ${desc.address || 'N/A'}`);
                    console.log(`      Type: ${desc.type || 'N/A'}`);
                    console.log(`      State: ${desc.state || 'N/A'}`);

                    // Extract region from hostname if possible
                    const hostname = desc.host || desc.address || '';
                    if (hostname.includes('.mongodb.net')) {
                        // Atlas hostname format: cluster0-shard-00-00.xxxxx.mongodb.net
                        // The xxxxx part might contain region info
                        const parts = hostname.split('.');
                        if (parts.length >= 2) {
                            const subdomain = parts[0];
                            console.log(`      Subdomain: ${subdomain}`);

                            // Check if it's a sharded cluster
                            if (subdomain.includes('shard')) {
                                const shardParts = subdomain.split('-');
                                console.log(`      Shard: ${shardParts[1] || 'N/A'}`);
                                console.log(`      Replica: ${shardParts[2] || 'N/A'}`);
                            }
                        }
                    }
                }
            });
        }

        // Get database information
        const dbName = mongoose.connection.db.databaseName;
        const stats = await mongoose.connection.db.stats();
        console.log(`\n📊 Database Information:`);
        console.log(`   Name: ${dbName}`);
        console.log(`   Collections: ${stats.collections || 0}`);
        console.log(`   Data Size: ${((stats.dataSize || 0) / 1024 / 1024).toFixed(2)} MB`);
        console.log(`   Storage Size: ${((stats.storageSize || 0) / 1024 / 1024).toFixed(2)} MB`);

        console.log(`\n💡 To see the exact region:`);
        console.log(`   1. Go to MongoDB Atlas: https://cloud.mongodb.com/`);
        console.log(`   2. Navigate to your cluster (cluster0)`);
        console.log(`   3. Check the "Configuration" or "Overview" tab`);
        console.log(`   4. Look for "Cloud Provider & Region" section`);

        await mongoose.disconnect();
        console.log('\n✅ Region check completed!');
        process.exit(0);
    } catch (error) {
        console.error('\n❌ Error checking region:');
        console.error(`   ${error.message}`);

        if (error.name === 'MongoServerSelectionError') {
            console.error('\n💡 Connection failed. Check:');
            console.error('   - Network connectivity');
            console.error('   - IP whitelist in MongoDB Atlas');
            console.error('   - Connection string is correct');
        }

        process.exit(1);
    }
}

checkRegion();

