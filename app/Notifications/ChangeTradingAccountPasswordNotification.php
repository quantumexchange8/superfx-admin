<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChangeTradingAccountPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $user;
    protected $meta_login;
    protected $master_password;
    protected $investor_password;
    public function __construct($user, $meta_login, $master_password, $investor_password)
    {
        $this->user = $user;
        $this->meta_login = $meta_login;
        $this->master_password = $master_password;
        $this->investor_password = $investor_password;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('SuperFin - Trading Account Password Changed')
            ->greeting('Dear ' . $this->user->email)
            ->line('This is a notification to inform you, your Main Password or Investor Password has been change.')
            ->line('Please check the credentials below to check the changes in your Main Password or Investor Password.')
            ->line('Login: ' . $this->meta_login)
            ->line('Main Password: ' . ($this->master_password ? $this->master_password : '-'))
            ->line('Investor Password: ' . ($this->investor_password ? $this->investor_password : '-'))
            ->line('Thank you for using our application!');
    }

    public function toArray($notifiable): array
    {
        return [];
    }
}
