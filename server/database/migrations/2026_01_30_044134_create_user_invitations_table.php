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
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            
            // Invitation details
            $table->string('email')->index();
            $table->string('role')->default('faculty');
            $table->string('department')->nullable();
            
            // Validation
            $table->string('token')->unique();
            $table->timestamp('expires_at');
            
            // Status
            $table->foreignId('invited_by')->constrained('users')->onDelete('cascade');
            $table->boolean('used')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
