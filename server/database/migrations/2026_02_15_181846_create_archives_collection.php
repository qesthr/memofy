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
        Schema::create('archives', function (Blueprint $table) {
            $table->id(); // Added primary key as it's common practice and was in the original table
            $table->string('item_id'); // Original ID
            $table->string('item_type'); // 'memo', 'event', 'user'
            $table->string('archived_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            
            // Filtering metadata for role-based access
            $table->string('sender_id')->nullable();
            $table->string('recipient_id')->nullable();
            $table->string('created_by')->nullable();
            $table->string('role')->nullable();
            $table->string('department')->nullable();
            $table->string('department_id')->nullable();
            
            // The full resource data
            $table->json('payload');
            
            // Indexing for performance
            $table->index('item_id');
            $table->index('item_type');
            $table->index('archived_by');
            $table->index('archived_at');
            $table->timestamps(); // Keep timestamps as they were in the original table
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archives');
    }
};
