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
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Memo notifications
            $table->boolean('memo_approved')->default(true);
            $table->boolean('memo_rejected')->default(true);
            $table->boolean('memo_received')->default(true);
            $table->boolean('memo_acknowledged')->default(true);
            
            // Calendar notifications
            $table->boolean('calendar_invitation')->default(true);
            $table->boolean('calendar_updated')->default(true);
            $table->boolean('calendar_response')->default(true);
            $table->boolean('calendar_secretary_created')->default(true);
            
            // Profile notifications
            $table->boolean('profile_updated')->default(true);
            
            // General settings
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
    }
};
