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
        Schema::table('calendar_events', function (Blueprint $table) {
            if (!Schema::hasColumn('calendar_events', 'source')) {
                $table->string('source')->default('MEMOFY'); // MEMOFY, GOOGLE
            }
            if (Schema::hasColumn('calendar_events', 'participants')) {
                $table->dropColumn('participants');
            }
        });

        Schema::dropIfExists('calendar_event_participants');
        Schema::create('calendar_event_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_event_id')->constrained('calendar_events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending'); // pending, accepted, declined
            $table->timestamps();

            $table->unique(['calendar_event_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_event_participants');

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropColumn('source');
            $table->json('participants')->nullable()->after('description');
        });
    }
};
