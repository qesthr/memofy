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
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            
            // Signature details
            $table->string('role_title')->unique(); // e.g., "President", "VP Academic Affairs"
            $table->string('display_name'); // Name of the person holding this role
            $table->text('image_url'); // Signature image (data URL or path)
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0); // Display order
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes
            $table->index('role_title');
            $table->index('is_active');
            $table->index('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};
