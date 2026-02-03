<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            
            // Authentication fields
            $table->string('google_id')->nullable();
            $table->string('email');
            $table->string('password')->nullable(); // Nullable for Google-only accounts
            
            // Personal information
            $table->string('first_name');
            $table->string('last_name');
            $table->string('full_name')->nullable();
            
            // Role and department
            $table->string('role')->default('faculty'); // admin, head, faculty, staff
            $table->string('department')->nullable();
            $table->string('employee_id')->nullable();
            
            // Profile
            $table->text('profile_picture')->nullable(); // Can store data URL or path
            
            // Account status
            $table->boolean('is_active')->default(true);
            
            // Unique Indexes with explicit names and partial expressions for MongoDB
            // This allows multiple nulls by only indexing when the field exists and is not null
            $table->index('email', 'users_email_unique', null, ['unique' => true]);
            $table->index('google_id', 'users_google_id_unique', null, [
                'unique' => true, 
                'partialFilterExpression' => ['google_id' => ['$type' => 'string']]
            ]);
            $table->index('employee_id', 'users_employee_id_unique', null, [
                'unique' => true, 
                'partialFilterExpression' => ['employee_id' => ['$type' => 'string']]
            ]);
            
            // Security - Brute force protection
            $table->integer('login_attempts')->default(0);
            $table->timestamp('lock_until')->nullable();
            $table->integer('violation_count')->default(0); // Track repeated lockouts
            
            // Timestamps
            $table->timestamp('last_login')->nullable();
            $table->timestamp('last_failed_login')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('role');
            $table->index('department');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
