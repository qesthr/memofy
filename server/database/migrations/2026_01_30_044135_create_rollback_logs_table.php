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
        Schema::create('rollback_logs', function (Blueprint $table) {
            $table->id();
            
            // Operation tracking
            $table->string('operation_id')->index(); // UUID or reference ID
            $table->string('operation_type'); // memo_approval, memo_rejection, memo_deletion, user_deletion, calendar_event_creation
            
            // State snapshots
            $table->json('before_state'); // State before operation
            $table->json('after_state');  // State after operation
            
            // Audit trail
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('timestamp')->useCurrent();
            
            // Rollback tracking
            $table->string('status')->default('completed'); // completed, rolled_back
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rolled_back_at')->nullable();
            $table->text('rollback_reason')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('operation_id');
            $table->index('operation_type');
            $table->index('status');
            $table->index('timestamp');
            $table->index(['operation_type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rollback_logs');
    }
};
