<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $invitation;
    public $password;
    public $setupUrl;

    /**
     * Create a new message instance.
     */
    public function __construct($invitation, $password = null)
    {
        $this->invitation = $invitation;
        $this->password = $password;
        
        $frontendUrl = config('app.frontend_url', 'http://localhost:5174');
        if ($invitation->token) {
            $this->setupUrl = $frontendUrl . '/auth/setup-password?token=' . $invitation->token;
        } else {
            $this->setupUrl = $frontendUrl . '/login';
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Memofy - Account Setup',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.user-invitation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
