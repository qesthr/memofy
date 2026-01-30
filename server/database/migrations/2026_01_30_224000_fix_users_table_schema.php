<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken()->after('violation_count');
            }
            
            // Fixed columns from previous session if they are missing
            if (!Schema::hasColumn('users', 'google_calendar_token')) {
                $table->text('google_calendar_token')->nullable()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'google_calendar_refresh_token')) {
                $table->text('google_calendar_refresh_token')->nullable()->after('google_calendar_token');
            }
            if (!Schema::hasColumn('users', 'google_calendar_token_expires_at')) {
                $table->timestamp('google_calendar_token_expires_at')->nullable()->after('google_calendar_refresh_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['remember_token', 'google_calendar_token', 'google_calendar_refresh_token', 'google_calendar_token_expires_at']);
        });
    }
};
