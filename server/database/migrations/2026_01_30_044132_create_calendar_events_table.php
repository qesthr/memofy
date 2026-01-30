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
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->id();
            
            // Event details
            $table->string('title');
            $table->dateTime('start');
            $table->dateTime('end');
            $table->boolean('all_day')->default(false);
            $table->string('category')->default('standard'); // urgent, high, standard, meeting, deadline, reminder, low, archived
            $table->text('description')->nullable();
            
            // Participants - JSON structure: {departments: [], emails: []}
            $table->json('participants')->nullable();
            
            // Relations
            $table->foreignId('memo_id')->nullable()->constrained('memos')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            
            // Status
            $table->string('status')->default('scheduled'); // scheduled, sent, cancelled
            
            // Google Calendar integration - JSON: {email: googleEventId}
            $table->json('google_calendar_event_ids')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('start');
            $table->index('end');
            $table->index('category');
            $table->index('created_by');
            $table->index(['start', 'end']); // Compound index for range queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
