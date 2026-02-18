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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('notifiable_type'); // notifiable user type
            $table->unsignedBigInteger('notifiable_id'); // notifiable user id
            $table->string('type'); // notification type
            $table->text('data'); // JSON notification data
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
            $table->index('created_at');
        });

        // Create notification preferences table
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Notification type preferences (boolean flags)
            $table->boolean('memo_approved')->default(true);
            $table->boolean('memo_rejected')->default(true);
            $table->boolean('memo_received')->default(true);
            $table->boolean('calendar_invitation')->default(true);
            $table->boolean('calendar_updated')->default(true);
            $table->boolean('profile_updated')->default(true);
            $table->boolean('memo_acknowledged')->default(true); // For secretaries
            
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(true);
            
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
