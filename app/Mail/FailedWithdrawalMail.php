<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class FailedWithdrawalMail extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private $user;
    private $transaction;
    private $platform;

    public function __construct($user, $transaction, $platform)
    {
        $this->user = $user;
        $this->transaction = $transaction;
        $this->platform = $platform;

        // queue
        $this->queue = 'failed_withdrawal_email';
    }

    public function build(): FailedWithdrawalMail
    {
        return $this->view('emails.failed-withdrawal')
            ->with([
                'user' => $this->user,
                'transaction' => $this->transaction,
                'platform' => $this->platform,
            ])
            ->subject('Failed Withdrawal Notification');
    }

    public function attachments(): array
    {
        return [];
    }
}
