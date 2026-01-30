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
        if (!Schema::hasTable('memos')) {
            Schema::create('memos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sender_id');
                $table->unsignedBigInteger('recipient_id')->nullable(); 
                
                $table->string('subject');
                $table->text('content')->nullable();
                
                $table->string('status')->default('sent');
                $table->string('priority')->default('medium');
                
                $table->boolean('is_read')->default(false);
                $table->boolean('is_starred')->default(false);
                
                $table->timestamp('read_at')->nullable();
                $table->timestamp('scheduled_send_at')->nullable();
                
                $table->string('attachment_path')->nullable();

                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memos');
    }
};
