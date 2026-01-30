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
        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                
                // Setting key-value
                $table->string('key')->unique();
                $table->json('value'); // Store any type of value as JSON
                $table->text('description')->nullable();
                
                // Audit
                $table->unsignedBigInteger('updated_by')->nullable();
                
                $table->timestamps();
                
                // Indexes
                $table->index('key');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
