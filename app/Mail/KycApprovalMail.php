<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class KycApprovalMail extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private $user;

    public function __construct($user)
    {
        $this->user = $user;

        // queue
        $this->queue = 'kyc_approval_email';
    }

    public function build(): KycApprovalMail
    {
        return $this->view('emails.kyc-approval')
            ->with([
                'user' => $this->user,
            ])
            ->subject('KYC Approval Notification');
    }

    public function attachments(): array
    {
        return [];
    }
}
