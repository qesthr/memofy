const mongoose = require('mongoose');
const path = require('path');
require('dotenv').config({ path: path.resolve(__dirname, '../../.env') });

const User = require('../models/User');

async function checkDepartmentUsers() {
    const uri = process.env.MONGODB_URI || process.env.MONGO_URI || 'mongodb://127.0.0.1:27017/buksu_memo';

    try {
        await mongoose.connect(uri, { serverSelectionTimeoutMS: 10000 });
        console.log('Connected to MongoDB\n');

        // Get all faculty members
        const allFaculty = await User.find({ role: 'faculty' })
            .select('_id email firstName lastName role department')
            .sort({ department: 1, firstName: 1 })
            .lean();

        console.log(`Total Faculty Members: ${allFaculty.length}\n`);

        // Group by department
        const byDept = allFaculty.reduce((acc, user) => {
            const dept = (user.department || '').trim() || '(no department)';
            if (!acc[dept]) {
                acc[dept] = [];
            }
            acc[dept].push(user);
            return acc;
        }, {});

        console.log('Faculty Members by Department:');
        console.log('='.repeat(60));

        Object.keys(byDept).sort().forEach(dept => {
            const members = byDept[dept];
            console.log(`\n${dept} (${members.length} faculty):`);
            members.forEach(user => {
                console.log(`  • ${user.firstName || ''} ${user.lastName || ''}`.trim() || user.email);
                console.log(`    Email: ${user.email}`);
                console.log(`    Department: "${user.department}"`);
                console.log(`    ID: ${user._id}`);
            });
        });

        // Check for Food Technology specifically
        console.log('\n' + '='.repeat(60));
        console.log('Food Technology Department Search:');
        console.log('='.repeat(60));

        const foodTechVariations = [
            'Food Technology',
            'food technology',
            'FOOD TECHNOLOGY',
            'FoodTechnology',
            'food-technology',
            'Food Technology ',
            ' Food Technology'
        ];

        foodTechVariations.forEach(variation => {
            const users = allFaculty.filter(u =>
                (u.department || '').trim().toLowerCase() === variation.trim().toLowerCase()
            );
            if (users.length > 0) {
                console.log(`\nFound ${users.length} faculty with department "${variation}":`);
                users.forEach(user => {
                    console.log(`  • ${user.firstName} ${user.lastName} (${user.email})`);
                });
            }
        });

        // Case-insensitive search
        const foodTechRegex = /food\s*technology/i;
        const foodTechUsers = allFaculty.filter(u =>
            foodTechRegex.test(u.department || '')
        );

        if (foodTechUsers.length > 0) {
            console.log(`\n\nTotal faculty matching "Food Technology" (case-insensitive): ${foodTechUsers.length}`);
            foodTechUsers.forEach(user => {
                console.log(`  • ${user.firstName} ${user.lastName}`);
                console.log(`    Email: ${user.email}`);
                console.log(`    Department (exact): "${user.department}"`);
            });
        } else {
            console.log('\n\nNo faculty found matching "Food Technology"');
        }

        // Check all unique department values
        const uniqueDepts = [...new Set(allFaculty.map(u => (u.department || '').trim()).filter(d => d)))];
        console.log('\n' + '='.repeat(60));
        console.log('All Unique Department Values:');
        console.log('='.repeat(60));
        uniqueDepts.sort().forEach(dept => {
            const count = allFaculty.filter(u => (u.department || '').trim() === dept).length;
            console.log(`"${dept}" - ${count} faculty`);
        });

        await mongoose.disconnect();
        console.log('\nDisconnected from MongoDB');
    } catch (error) {
        console.error('Error:', error);
        process.exit(1);
    }
}

checkDepartmentUsers();

