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
        // 1. Backfill Memos
        $memos = \App\Models\Memo::withTrashed()
            ->whereNotNull('deleted_at')
            ->get();

        foreach ($memos as $memo) {
            // Avoid duplicates
            if (\App\Models\Archive::where('item_id', (string)$memo->id)->where('item_type', 'memo')->exists()) {
                continue;
            }

            \App\Models\Archive::create([
                'item_id' => (string) $memo->id,
                'item_type' => 'memo',
                'archived_by' => (string) $memo->archived_by ?: (string) $memo->sender_id,
                'archived_at' => $memo->archived_at ?: $memo->deleted_at,
                'sender_id' => (string) $memo->sender_id,
                'recipient_id' => (string) $memo->recipient_id,
                'created_by' => (string) $memo->created_by,
                'payload' => $memo->toArray()
            ]);
        }

        // 2. Backfill Calendar Events
        $events = \App\Models\CalendarEvent::withTrashed()
            ->whereNotNull('deleted_at')
            ->get();

        foreach ($events as $event) {
            if (\App\Models\Archive::where('item_id', (string)$event->id)->where('item_type', 'event')->exists()) {
                continue;
            }

            \App\Models\Archive::create([
                'item_id' => (string) $event->id,
                'item_type' => 'event',
                'archived_by' => (string) $event->archived_by ?: (string) $event->created_by,
                'archived_at' => $event->archived_at ?: $event->deleted_at,
                'created_by' => (string) $event->created_by,
                'payload' => $event->toArray()
            ]);
        }

        // 3. Backfill Users
        $users = \App\Models\User::where('is_active', false)->get();

        foreach ($users as $user) {
            if (\App\Models\Archive::where('item_id', (string)$user->id)->where('item_type', 'user')->exists()) {
                continue;
            }

            \App\Models\Archive::create([
                'item_id' => (string) $user->id,
                'item_type' => 'user',
                'archived_by' => (string) $user->archived_by,
                'archived_at' => $user->archived_at ?: $user->updated_at,
                'role' => (string) $user->role,
                'department' => (string) $user->department,
                'department_id' => (string) $user->department_id,
                'payload' => $user->toArray()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No auto-down for data migration
    }
};
