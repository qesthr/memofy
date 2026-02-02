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
        if (!Schema::hasTable('rollback_logs')) {
            Schema::create('rollback_logs', function (Blueprint $table) {
                $table->id();
                
                // Operation tracking
                $table->string('operation_id');
                $table->string('operation_type');
                
                // State snapshots
                $table->json('before_state');
                $table->json('after_state');
                
                // Audit trail
                $table->unsignedBigInteger('performed_by');
                $table->timestamp('timestamp')->useCurrent();
                
                // Rollback tracking
                $table->string('status')->default('completed');
                $table->unsignedBigInteger('rolled_back_by')->nullable();
                $table->timestamp('rolled_back_at')->nullable();
                $table->text('rollback_reason')->nullable();
                
                $table->timestamps();

                $table->index('operation_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rollback_logs');
    }
};
