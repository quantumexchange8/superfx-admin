<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class WithdrawalApprovalMail extends Mailable implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private $user;
    private $meta_login;
    private $account_type;
    private $amount;
    private $account_no;
    private $payment_platform;
    private $platform;

    public function __construct($user, $meta_login, $account_type, $amount, $account_no, $payment_platform, $platform)
    {
        $this->user = $user;
        $this->meta_login = $meta_login;
        $this->account_type = $account_type;
        $this->amount = $amount;
        $this->account_no = $account_no;
        $this->payment_platform = $payment_platform;
        $this->platform = $platform;

        // queue
        $this->queue = 'withdrawal_approval_email';
    }

    public function build(): WithdrawalApprovalMail
    {
        return $this->view('emails.withdrawal-approval')
            ->with([
                'user' => $this->user,
                'meta_login' => $this->meta_login,
                'account_type' => $this->account_type,
                'amount' => $this->amount,
                'account_no' => $this->account_no,
                'payment_platform' => $this->payment_platform,
                'platform' => $this->platform,
            ])
            ->subject('Withdrawal Approval Notification');
    }

    public function attachments(): array
    {
        return [];
    }
}
