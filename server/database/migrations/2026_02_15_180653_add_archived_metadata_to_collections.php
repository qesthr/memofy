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
        Schema::table('memos', function ($table) {
            $table->timestamp('archived_at')->nullable();
            $table->string('archived_by')->nullable();
        });

        Schema::table('calendar_events', function ($table) {
            $table->timestamp('archived_at')->nullable();
            $table->string('archived_by')->nullable();
        });

        Schema::table('users', function ($table) {
            $table->timestamp('archived_at')->nullable();
            $table->string('archived_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memos', function ($table) {
            $table->dropColumn(['archived_at', 'archived_by']);
        });

        Schema::table('calendar_events', function ($table) {
            $table->dropColumn(['archived_at', 'archived_by']);
        });

        Schema::table('users', function ($table) {
            $table->dropColumn(['archived_at', 'archived_by']);
        });
    }
};
