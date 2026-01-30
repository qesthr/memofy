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
        if (!Schema::hasTable('user_activity_logs')) {
            Schema::create('user_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('actor_email')->nullable();
                $table->string('actor_role')->nullable();
                $table->string('actor_department')->nullable();
                $table->string('action');
                $table->string('target')->nullable();
                $table->unsignedBigInteger('target_id')->nullable();
                $table->text('description')->nullable();
                $table->text('details')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();

                $table->index('actor_id');
                $table->index('action');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
