<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail fallback for the daily study reminder when no browser is subscribed to push. */
class StudyReminderMail extends Notification
{
    /** @param  array{title: string, body: string, url: string}  $message */
    public function __construct(private readonly array $message) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->message['title'])
            ->line($this->message['body'])
            ->action($this->message['title'], url($this->message['url']));
    }
}
