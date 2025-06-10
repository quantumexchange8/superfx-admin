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

    public function __construct($user, $transaction)
    {
        $this->user = $user;
        $this->transaction = $transaction;

        // queue
        $this->queue = 'failed_withdrawal_email';
    }

    public function build(): FailedWithdrawalMail
    {
        return $this->view('emails.failed-withdrawal')
            ->with([
                'user' => $this->user,
                'transaction' => $this->transaction,
            ])
            ->subject('Failed Withdrawal Notification');
    }

    public function attachments(): array
    {
        return [];
    }
}
