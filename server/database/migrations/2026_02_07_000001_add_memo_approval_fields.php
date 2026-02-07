<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds approval workflow fields to memos table
     */
    public function up(): void
    {
        Schema::table('memos', function (Blueprint $table) {
            // Approval workflow fields
            if (!Schema::hasColumn('memos', 'status')) {
                $table->string('status')->default('sent')->after('priority');
            }
            
            if (!Schema::hasColumn('memos', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('status');
            }
            
            if (!Schema::hasColumn('memos', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            
            if (!Schema::hasColumn('memos', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_at');
            }
            
            if (!Schema::hasColumn('memos', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            
            if (!Schema::hasColumn('memos', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
            
            // Additional memo fields
            if (!Schema::hasColumn('memos', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('recipient_id');
            }
            
            if (!Schema::hasColumn('memos', 'all_day_event')) {
                $table->boolean('all_day_event')->default(false)->after('scheduled_send_at');
            }
            
            if (!Schema::hasColumn('memos', 'schedule_end_at')) {
                $table->timestamp('schedule_end_at')->nullable()->after('scheduled_send_at');
            }
            
            if (!Schema::hasColumn('memos', 'signature_id')) {
                $table->unsignedBigInteger('signature_id')->nullable()->after('department_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('memos', function (Blueprint $table) {
            $columns = [
                'approved_by',
                'approved_at',
                'rejected_by',
                'rejected_at',
                'rejection_reason',
                'department_id',
                'all_day_event',
                'schedule_end_at',
                'signature_id'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('memos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
