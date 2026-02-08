<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MemoNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $memo;
    public $recipient;
    public $sender;
    public $type; // 'new_memo', 'acknowledgment_reminder', 'memo_approved'

    /**
     * Create a new message instance.
     */
    public function __construct($memo, $recipient, $sender, $type = 'new_memo')
    {
        $this->memo = $memo;
        $this->recipient = $recipient;
        $this->sender = $sender;
        $this->type = $type;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = $this->getSubject();
        
        return $this->subject($subject)
                    ->view('emails.memo-notification')
                    ->with([
                        'memo' => $this->memo,
                        'recipient' => $this->recipient,
                        'sender' => $this->sender,
                        'type' => $this->type,
                    ]);
    }

    /**
     * Get email subject based on type
     */
    protected function getSubject()
    {
        switch ($this->type) {
            case 'new_memo':
                return '[MEMO] ' . $this->memo->subject;
            case 'acknowledgment_reminder':
                return '[REMINDER] Please acknowledge: ' . $this->memo->subject;
            case 'memo_approved':
                return '[APPROVED] ' . $this->memo->subject;
            default:
                return '[MEMO] ' . $this->memo->subject;
        }
    }
}
