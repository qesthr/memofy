<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('user_activity_logs', 'actor_id')) {
                // Remove old user_id if it exists and we're switching to actor_id
                if (Schema::hasColumn('user_activity_logs', 'user_id')) {
                    $table->dropColumn('user_id');
                }
                
                $table->unsignedBigInteger('actor_id')->nullable()->after('id');
                $table->string('actor_email')->nullable()->after('actor_id');
                $table->string('actor_role')->nullable()->after('actor_email');
                $table->string('actor_department')->nullable()->after('actor_role');
                $table->string('target')->nullable()->after('action');
                $table->unsignedBigInteger('target_id')->nullable()->after('target');
                
                $table->index('actor_id');
            }
            
            // Ensure details is text for compatibility as per the context
            if (Schema::hasColumn('user_activity_logs', 'details')) {
                $table->text('details')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_activity_logs', function (Blueprint $table) {
            $table->dropColumn(['actor_id', 'actor_email', 'actor_role', 'actor_department', 'target', 'target_id']);
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
        });
    }
};
