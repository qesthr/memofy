<?php

namespace App\Console\Commands;

use App\Models\Memo;
use App\Models\User;
use App\Models\MemoAcknowledgment;
use App\Services\NotificationService;
use App\Services\ActivityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendScheduledMemos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memos:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and send memos that are scheduled for the current time';

    protected $notificationService;
    protected $logger;

    public function __construct(NotificationService $notificationService, ActivityLogger $logger)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
        $this->logger = $logger;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = now();
        $this->info("Checking for scheduled memos due at {$now}...");

        $memos = Memo::where('status', 'scheduled')
                     ->where('scheduled_send_at', '<=', $now)
                     ->get();

        if ($memos->isEmpty()) {
            $this->info("No scheduled memos due.");
            return 0;
        }

        $this->info("Processing " . $memos->count() . " memos...");

        foreach ($memos as $memo) {
            $this->processMemo($memo);
        }

        $this->info("Completed processing scheduled memos.");
        return 0;
    }

    protected function processMemo(Memo $memo)
    {
        DB::transaction(function () use ($memo) {
            // Update status to 'archived' as requested after sending
            // We set it to 'sent' first internally or just go straight to 'archived' 
            // but we need recipients to see it as a 'sent' memo.
            // In this system, 'archived' is the terminal state.
            $memo->update([
                'status' => 'archived',
                'sent_at' => now()
            ]);

            // Create acknowledgment records
            $recipients = [];
            if ($memo->department_id) {
                $departmentUsers = User::where('department_id', $memo->department_id)
                                        ->where('id', '!=', $memo->sender_id)
                                        ->get();
                
                foreach ($departmentUsers as $deptUser) {
                    MemoAcknowledgment::create([
                        'memo_id' => $memo->id,
                        'recipient_id' => $deptUser->id,
                        'is_acknowledged' => false,
                        'sent_at' => now()
                    ]);
                    $recipients[] = $deptUser;
                }
            } else if ($memo->recipient_id) {
                $recipient = User::find($memo->recipient_id);
                if ($recipient) {
                    MemoAcknowledgment::create([
                        'memo_id' => $memo->id,
                        'recipient_id' => $memo->recipient_id,
                        'is_acknowledged' => false,
                        'sent_at' => now()
                    ]);
                    $recipients[] = $recipient;
                }
            }

            // Sync context for notification
            $admin = User::where('role', 'admin')->first(); // Use some system user or logic
            
            // Notify recipients
            if (!empty($recipients)) {
                $this->notificationService->notifyMemoRecipients($admin, $memo, $recipients);
            }

            // Update calendar event status to 'sent'
            $calendarEvent = \App\Models\CalendarEvent::where('memo_id', $memo->id)->first();
            if ($calendarEvent) {
                $calendarEvent->update(['status' => 'sent']);
                
                // Add recipients to participants now that it's sent
                foreach ($recipients as $recipient) {
                    \App\Models\CalendarEventParticipant::updateOrCreate(
                        [
                            'calendar_event_id' => $calendarEvent->id,
                            'user_id' => $recipient->id
                        ],
                        ['status' => 'pending']
                    );
                }
            }

            $this->info("Sent memo: {$memo->subject} (ID: {$memo->id})");
        });
    }
}
