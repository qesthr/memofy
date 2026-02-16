<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to add performance-optimizing indexes.
     * These indexes are critical for reducing query time from 10-20 seconds to milliseconds.
     */
    public function up(): void
    {
        // =========================================================================
        // MEMOS TABLE INDEXES
        // =========================================================================
        
        // Composite index for sender queries (most common in secretary/admin views)
        if (Schema::hasTable('memos')) {
            Schema::table('memos', function ($table) {
                // Index for sender_id filtering (used in sent/drafts views)
                if (!$this->hasIndex('memos', 'idx_memos_sender_id', 'sender_id')) {
                    $table->index('sender_id', 'idx_memos_sender_id');
                }
                
                // Index for recipient_id filtering (used in received views)
                if (!$this->hasIndex('memos', 'idx_memos_recipient_id', 'recipient_id')) {
                    $table->index('recipient_id', 'idx_memos_recipient_id');
                }
                
                // Index for status filtering (used in approval workflow)
                if (!$this->hasIndex('memos', 'idx_memos_status', 'status')) {
                    $table->index('status', 'idx_memos_status');
                }
                
                // Index for priority filtering
                if (!$this->hasIndex('memos', 'idx_memos_priority', 'priority')) {
                    $table->index('priority', 'idx_memos_priority');
                }
                

                

                
                // Index for department_id filtering
                if (!$this->hasIndex('memos', 'idx_memos_department_id', 'department_id')) {
                    $table->index('department_id', 'idx_memos_department_id');
                }
                
                // Index for scheduled_send_at (calendar events)
                if (!$this->hasIndex('memos', 'idx_memos_scheduled_send_at', 'scheduled_send_at')) {
                    $table->index('scheduled_send_at', 'idx_memos_scheduled_send_at');
                }
            });
        }
        
        // =========================================================================
        // USERS TABLE INDEXES
        // =========================================================================
        
        if (Schema::hasTable('users')) {
            Schema::table('users', function ($table) {
                // Composite index for role + department filtering ( secretaries view)
                if (!$this->hasIndex('users', 'idx_users_role_department', ['role', 'department'])) {
                    $table->index(['role', 'department'], 'idx_users_role_department');
                }
                
                // Composite index for role + is_active filtering
                if (!$this->hasIndex('users', 'idx_users_role_active', ['role', 'is_active'])) {
                    $table->index(['role', 'is_active'], 'idx_users_role_active');
                }
                
                // Composite index for department + is_active filtering
                if (!$this->hasIndex('users', 'idx_users_department_active', ['department', 'is_active'])) {
                    $table->index(['department', 'is_active'], 'idx_users_department_active');
                }
                
                // Index for department_id (MongoDB-style)
                if (!$this->hasIndex('users', 'idx_users_department_id', 'department_id')) {
                    $table->index('department_id', 'idx_users_department_id');
                }
                
                // Index for role_id (foreign key to roles table)
                if (!$this->hasIndex('users', 'idx_users_role_id', 'role_id')) {
                    $table->index('role_id', 'idx_users_role_id');
                }
            });
        }
        
        // =========================================================================
        // USER_ACTIVITY_LOGS TABLE INDEXES
        // =========================================================================
        
        if (Schema::hasTable('user_activity_logs')) {
            Schema::table('user_activity_logs', function ($table) {
                // Composite index for actor + date filtering (activity logs)
                if (!$this->hasIndex('user_activity_logs', 'idx_logs_actor_created', ['actor_id', 'created_at'])) {
                    $table->index(['actor_id', 'created_at'], 'idx_logs_actor_created');
                }
                
                // Composite index for action + date filtering
                if (!$this->hasIndex('user_activity_logs', 'idx_logs_action_created', ['action', 'created_at'])) {
                    $table->index(['action', 'created_at'], 'idx_logs_action_created');
                }
                
                // Index for actor_email (used in search)
                if (!$this->hasIndex('user_activity_logs', 'idx_logs_actor_email', 'actor_email')) {
                    $table->index('actor_email', 'idx_logs_actor_email');
                }
                
                // Index for actor_department (for department-scoped views)
                if (!$this->hasIndex('user_activity_logs', 'idx_logs_actor_department', 'actor_department')) {
                    $table->index('actor_department', 'idx_logs_actor_department');
                }
            });
        }
        
        // =========================================================================
        // CALENDAR_EVENTS TABLE INDEXES
        // =========================================================================
        
        if (Schema::hasTable('calendar_events')) {
            Schema::table('calendar_events', function ($table) {
                // Index for created_by (user's own events)
                if (!$this->hasIndex('calendar_events', 'idx_events_created_by', 'created_by')) {
                    $table->index('created_by', 'idx_events_created_by');
                }
                
                // Index for memo_id (events linked to memos)
                if (!$this->hasIndex('calendar_events', 'idx_events_memo_id', 'memo_id')) {
                    $table->index('memo_id', 'idx_events_memo_id');
                }
                
                // Index for status
                if (!$this->hasIndex('calendar_events', 'idx_events_status', 'status')) {
                    $table->index('status', 'idx_events_status');
                }
            });
        }
        
        // =========================================================================
        // CALENDAR_EVENT_PARTICIPANTS TABLE INDEXES
        // =========================================================================
        
        if (Schema::hasTable('calendar_event_participants')) {
            Schema::table('calendar_event_participants', function ($table) {
                // Composite index for user + event filtering (user's invitations)
                if (!$this->hasIndex('calendar_event_participants', 'idx_participants_user_event', ['user_id', 'calendar_event_id'])) {
                    $table->index(['user_id', 'calendar_event_id'], 'idx_participants_user_event');
                }
                
                // Index for user_id (events user is invited to)
                if (!$this->hasIndex('calendar_event_participants', 'idx_participants_user_id', 'user_id')) {
                    $table->index('user_id', 'idx_participants_user_id');
                }
                
                // Index for calendar_event_id (participants of an event)
                if (!$this->hasIndex('calendar_event_participants', 'idx_participants_event_id', 'calendar_event_id')) {
                    $table->index('calendar_event_id', 'idx_participants_event_id');
                }
            });
        }
        
        // =========================================================================
        // NOTIFICATIONS TABLE INDEXES (MongoDB)
        // =========================================================================
        
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function ($table) {
                // Composite index for user notifications
                if (!$this->hasIndex('notifications', 'idx_notifications_notifiable', ['notifiable_type', 'notifiable_id'])) {
                    $table->index(['notifiable_type', 'notifiable_id'], 'idx_notifications_notifiable');
                }
                
                // Composite index for unread notifications (most common filter)
                if (!$this->hasIndex('notifications', 'idx_notifications_unread', ['notifiable_type', 'notifiable_id', 'read_at'])) {
                    $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notifications_unread');
                }
                
                // Index for created_at (sorting by newest)
                if (!$this->hasIndex('notifications', 'idx_notifications_created', 'created_at')) {
                    $table->index('created_at', 'idx_notifications_created');
                }
                
                // Index for type (filtering by notification type)
                if (!$this->hasIndex('notifications', 'idx_notifications_type', 'type')) {
                    $table->index('type', 'idx_notifications_type');
                }
            });
        }
    }
    
    /**
     * Check if an index exists on a table.
     */
    protected function hasIndex(string $table, string $indexName, $columns = null): bool
    {
        try {
            // For MongoDB, use the raw collection to check indexes
            if (DB::connection()->getDriverName() === 'mongodb') {
                $collection = DB::connection()->getCollection($table);
                foreach ($collection->listIndexes() as $index) {
                    // Check by name
                    if ($index->getName() === $indexName) {
                        return true;
                    }
                    
                    // Check by columns if provided
                    if ($columns) {
                        $keys = (array)$index->getKey();
                        $indexFields = array_keys($keys);
                        $targetFields = is_array($columns) ? $columns : [$columns];
                        
                        if ($indexFields === $targetFields) {
                            return true;
                        }
                    }
                }
            }

            if (DB::connection()->getDriverName() === 'mysql') {
                $indexes = DB::select("SHOW INDEX FROM {$table}");
                foreach ($indexes as $index) {
                    if ($index->Key_name === $indexName) {
                        return true;
                    }
                }
            } elseif (DB::connection()->getDriverName() === 'pgsql') {
                $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = ?", [$table]);
                foreach ($indexes as $index) {
                    if ($index->indexname === $indexName) {
                        return true;
                    }
                }
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes (optional - in production you might want to keep them)
        $indexesToDrop = [
            'memos' => [
                'idx_memos_sender_id',
                'idx_memos_recipient_id',
                'idx_memos_status',
                'idx_memos_priority',

                'idx_memos_department_id',
                'idx_memos_scheduled_send_at',
            ],
            'users' => [
                'idx_users_role_department',
                'idx_users_role_active',
                'idx_users_department_active',
                'idx_users_department_id',
                'idx_users_role_id',
            ],
            'user_activity_logs' => [
                'idx_logs_actor_created',
                'idx_logs_action_created',
                'idx_logs_actor_email',
                'idx_logs_actor_department',
            ],
            'calendar_events' => [
                'idx_events_created_by',
                'idx_events_memo_id',
                'idx_events_status',
            ],
            'calendar_event_participants' => [
                'idx_participants_user_event',
                'idx_participants_user_id',
                'idx_participants_event_id',
            ],
            'notifications' => [
                'idx_notifications_notifiable',
                'idx_notifications_unread',
                'idx_notifications_created',
                'idx_notifications_type',
            ],
        ];
        
        foreach ($indexesToDrop as $table => $indexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function ($blueprint) use ($indexes) {
                    foreach ($indexes as $indexName) {
                        try {
                            $blueprint->dropIndex($indexName);
                        } catch (\Exception $e) {
                            // Index might not exist, ignore
                        }
                    }
                });
            }
        }
    }
};
