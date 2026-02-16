<?php

namespace App\Services;

use App\Mail\MemoNotification;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a notification for a user
     */
    public function createNotification(User $user, string $type, array $data, string $link = null): Notification
    {
        // Check user preferences
        $preferences = NotificationPreference::getDefaultForUser($user->id);
        
        if (!$preferences->shouldReceive($type)) {
            Log::info('Notification skipped due to user preferences', [
                'user_id' => $user->id,
                'type' => $type
            ]);
            return null;
        }

        $notification = Notification::create([
            'type' => $type,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => array_merge($data, [
                'link' => $link,
                'icon' => $this->getIconForType($type),
                'title' => $this->getTitleForType($type)
            ]),
            'read_at' => null
        ]);

        // Send email if user wants email notifications
        if ($preferences->wantsEmail()) {
            $this->sendEmailNotification($user, $notification);
        }

        return $notification;
    }

    /**
     * Send memo approved notification
     */
    public function notifyMemoApproved(User $sender, User $creator, $memo): Notification
    {
        $data = [
            'memo_id' => $memo->id,
            'memo_subject' => $memo->subject,
            'sender_name' => $sender->first_name . ' ' . $sender->last_name,
            'message' => "Your memo '{$memo->subject}' has been approved and sent to recipients.",
            'priority' => $memo->priority
        ];

        return $this->createNotification(
            $creator,
            Notification::TYPE_MEMO_APPROVED,
            $data,
            "/{$creator->role}/memos?memoId={$memo->id}"
        );
    }

    /**
     * Send memo rejected notification
     */
    public function notifyMemoRejected(User $sender, User $creator, $memo, string $reason = null): Notification
    {
        $data = [
            'memo_id' => $memo->id,
            'memo_subject' => $memo->subject,
            'sender_name' => $sender->first_name . ' ' . $sender->last_name,
            'message' => "Your memo '{$memo->subject}' has been rejected.",
            'rejection_reason' => $reason,
            'priority' => $memo->priority
        ];

        return $this->createNotification(
            $creator,
            Notification::TYPE_MEMO_REJECTED,
            $data,
            "/{$creator->role}/memos?memoId={$memo->id}"
        );
    }

    /**
     * Send new memo received notification to recipients
     */
    public function notifyMemoReceived(User $sender, User $recipient, $memo): ?Notification
    {
        $data = [
            'memo_id' => $memo->id,
            'memo_subject' => $memo->subject,
            'sender_name' => $sender->first_name . ' ' . $sender->last_name,
            'sender_role' => $sender->role,
            'message' => "You have received a new memo from {$sender->first_name} {$sender->last_name}",
            'priority' => $memo->priority,
            'department' => $sender->department
        ];

        return $this->createNotification(
            $recipient,
            Notification::TYPE_MEMO_RECEIVED,
            $data,
            "/{$recipient->role}/memos?memoId={$memo->id}"
        );
    }

    /**
     * Send memo acknowledgment notification to secretary
     */
    public function notifyMemoAcknowledged(User $recipient, User $secretary, $memo): ?Notification
    {
        $data = [
            'memo_id' => $memo->id,
            'memo_subject' => $memo->subject,
            'recipient_name' => $recipient->first_name . ' ' . $recipient->last_name,
            'message' => "{$recipient->first_name} {$recipient->last_name} has acknowledged receipt of your memo.",
            'acknowledged_at' => now()->toIso8601String()
        ];

        return $this->createNotification(
            $secretary,
            Notification::TYPE_MEMO_ACKNOWLEDGED,
            $data,
            "/{$secretary->role}/memos?memoId={$memo->id}"
        );
    }

    /**
     * Send calendar invitation notification to multiple recipients
     */
    public function notifyCalendarInvitation(User $host, $event, array $recipients): array
    {
        $sent = [];
        
        foreach ($recipients as $recipient) {
            $data = [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'host_name' => $host->first_name . ' ' . $host->last_name,
                'event_start' => $event->start,
                'event_end' => $event->end,
                'message' => "You have been invited to: {$event->title}",
                'description' => $event->description
            ];

            $notification = $this->createNotification(
                $recipient,
                Notification::TYPE_CALENDAR_INVITATION,
                $data,
                "/{$recipient->role}/calendar?event={$event->id}"
            );
            
            if ($notification) {
                $sent[] = $recipient->id;
            }
        }
        
        return $sent;
    }

    /**
     * Send calendar event updated notification to all participants
     */
    public function notifyCalendarUpdated(User $host, $event): void
    {
        $participants = $event->participants()->with('user')->where('user_id', '!=', $host->id)->get();
        
        foreach ($participants as $participant) {
            $data = [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'host_name' => $host->first_name . ' ' . $host->last_name,
                'message' => "Calendar event '{$event->title}' has been updated.",
                'event_start' => $event->start,
                'event_end' => $event->end
            ];

            $this->createNotification(
                $participant->user,
                Notification::TYPE_CALENDAR_UPDATED,
                $data,
                "/{$participant->user->role}/calendar?event={$event->id}"
            );
        }
    }

    /**
     * Send notification when someone responds to calendar invitation
     */
    public function notifyCalendarResponse(User $creator, $event, User $responder, string $response): void
    {
        $data = [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'responder_name' => $responder->first_name . ' ' . $responder->last_name,
            'response' => $response,
            'message' => "{$responder->first_name} {$responder->last_name} has {$response} your event invitation."
        ];

        $this->createNotification(
            $creator,
            Notification::TYPE_CALENDAR_RESPONSE,
            $data,
            "/{$creator->role}/calendar?event={$event->id}"
        );
    }

    /**
     * Send secretary profile update notification to admins
     */
    public function notifyProfileUpdate(User $secretary, User $admin, string $updateType): ?Notification
    {
        $messages = [
            'name' => 'updated their name',
            'email' => 'updated their email address',
            'department' => 'changed their department',
            'photo' => 'updated their profile photo',
            'signature' => 'updated their signature'
        ];

        $data = [
            'secretary_id' => $secretary->id,
            'secretary_name' => $secretary->first_name . ' ' . $secretary->last_name,
            'update_type' => $updateType,
            'message' => 'Secretary ' . $secretary->first_name . ' ' . $secretary->last_name . ' ' . ($messages[$updateType] ?? 'made profile changes') . '.'
        ];

        return $this->createNotification(
            $admin,
            Notification::TYPE_PROFILE_UPDATED,
            $data,
            "/admin/users/{$secretary->id}"
        );
    }

    /**
     * Send secretary-created calendar event notification to all relevant recipients
     */
    public function notifySecretaryCalendarEvent(User $secretary, $event, User $recipient): ?Notification
    {
        $data = [
            'event_id' => $event->id,
            'event_title' => $event->title,
            'creator_name' => $secretary->first_name . ' ' . $secretary->last_name,
            'event_start' => $event->start,
            'event_end' => $event->end,
            'message' => "New calendar event from {$secretary->first_name} {$secretary->last_name}: {$event->title}",
            'description' => $event->description,
            'is_secretary_created' => true
        ];

        return $this->createNotification(
            $recipient,
            Notification::TYPE_CALENDAR_SECRETARY_CREATED,
            $data,
            "/{$recipient->role}/calendar?event={$event->id}"
        );
    }

    /**
     * Notify all recipients of a memo (used when admin creates memo directly)
     */
    public function notifyMemoRecipients(User $sender, $memo, array $recipients): array
    {
        $sent = [];
        
        foreach ($recipients as $recipient) {
            $notification = $this->notifyMemoReceived($sender, $recipient, $memo);
            if ($notification) {
                $sent[] = $recipient->id;
            }
        }

        Log::info('Memo notifications sent', [
            'memo_id' => $memo->id,
            'recipients_notified' => count($sent)
        ]);

        return $sent;
    }

    /**
     * Send reminders to recipients who haven't acknowledged a memo
     */
    public function sendAcknowledgmentReminders($memo, User $sender, $recipients): array
    {
        $sent = [];
        
        foreach ($recipients as $recipient) {
            $data = [
                'memo_id' => $memo->id,
                'memo_subject' => $memo->subject,
                'sender_name' => $sender->first_name . ' ' . $sender->last_name,
                'message' => "Reminder: Please acknowledge receipt of memo: '{$memo->subject}'",
                'priority' => $memo->priority,
                'sender_role' => $sender->role
            ];

            $notification = $this->createNotification(
                $recipient,
                Notification::TYPE_MEMO_REMINDER,
                $data,
                "/{$recipient->role}/memos?memoId={$memo->id}"
            );
            
            if ($notification) {
                $sent[] = (string)$recipient->id;
            }
        }

        return [
            'sent' => count($sent),
            'recipients' => $sent
        ];
    }

    /**
     * Notify admin of profile update
     */
    public function notifyAdminsOfProfileUpdate(User $secretary, string $updateType): void
    {
        $admins = User::where('role', 'admin')->get();
        
        foreach ($admins as $admin) {
            $this->notifyProfileUpdate($secretary, $admin, $updateType);
        }
    }

    /**
     * Send email notification
     * Note: In development/testing environment, emails are not sent to avoid delivery errors
     * with dummy email addresses. Only in-app notifications are created.
     */
    protected function sendEmailNotification(User $user, Notification $notification): void
    {
        // Skip actual email sending in non-production environments
        // This prevents delivery errors with dummy/test email addresses
        if (app()->environment('local', 'development', 'testing')) {
            Log::info('Email notification skipped (development mode)', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'notification_type' => $notification->type,
                'notification_title' => $notification->data['title'] ?? 'N/A'
            ]);
            return;
        }

        // Only send actual emails in production environment
        try {
            Mail::to($user->email)->send(new MemoNotification(
                (object)[
                    'id' => $notification->data['memo_id'] ?? null,
                    'subject' => $notification->data['title'] ?? 'Notification',
                    'message' => $notification->data['message'] ?? '',
                    'priority' => $notification->data['priority'] ?? 'normal',
                    'created_at' => now(),
                    'attachments' => []
                ],
                $user,
                (object)[
                    'first_name' => 'System',
                    'last_name' => ''
                ],
                $notification->type
            ));
        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get icon for notification type
     */
    protected function getIconForType(string $type): string
    {
        return match($type) {
            Notification::TYPE_MEMO_APPROVED => '✅',
            Notification::TYPE_MEMO_REJECTED => '❌',
            Notification::TYPE_MEMO_RECEIVED => '📬',
            Notification::TYPE_MEMO_ACKNOWLEDGED => '👁️',
            Notification::TYPE_CALENDAR_INVITATION => '📅',
            Notification::TYPE_CALENDAR_UPDATED => '🔄',
            Notification::TYPE_PROFILE_UPDATED => '👤',
            Notification::TYPE_CALENDAR_SECRETARY_CREATED => '📅',
            Notification::TYPE_MEMO_REMINDER => '🔔',
            default => '🔔'
        };
    }

    /**
     * Get title for notification type
     */
    protected function getTitleForType(string $type): string
    {
        return match($type) {
            Notification::TYPE_MEMO_APPROVED => 'Memo Approved',
            Notification::TYPE_MEMO_REJECTED => 'Memo Rejected',
            Notification::TYPE_MEMO_RECEIVED => 'New Memo Received',
            Notification::TYPE_MEMO_ACKNOWLEDGED => 'Memo Acknowledged',
            Notification::TYPE_CALENDAR_INVITATION => 'Calendar Invitation',
            Notification::TYPE_CALENDAR_UPDATED => 'Calendar Event Updated',
            Notification::TYPE_PROFILE_UPDATED => 'Profile Updated',
            Notification::TYPE_CALENDAR_SECRETARY_CREATED => 'New Calendar Event',
            Notification::TYPE_MEMO_REMINDER => 'Acknowledgment Reminder',
            default => 'Notification'
        };
    }
}
