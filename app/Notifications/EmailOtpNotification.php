<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $otp
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your verification OTP')
            ->greeting('Hello!')
            ->line('Your email verification OTP is:')
            ->line($this->otp)
            ->line('This OTP expires in 10 minutes.')
            ->line('Do not share this OTP with anyone.');
    }
}
