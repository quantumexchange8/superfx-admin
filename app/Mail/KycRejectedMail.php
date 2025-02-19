<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class KycRejectedMail extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private $user;

    public function __construct($user)
    {
        $this->user = $user;

        // queue
        $this->queue = 'kyc_rejected_email';
    }

    public function build(): KycRejectedMail
    {
        return $this->view('emails.kyc-rejected')
            ->with([
                'user' => $this->user,
            ])
            ->subject('KYC Rejection Notification');
    }

    public function attachments(): array
    {
        return [];
    }
}
