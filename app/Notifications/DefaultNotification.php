<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DefaultNotification extends Notification
{
    use Queueable;

    private $link;
    private $subject;
    private $message;

    public function __construct($subject, $message, $link)
    {
        $this->link = $link;
        $this->subject = $subject;
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject($this->subject)
                    ->line(new \Illuminate\Support\HtmlString($this->message));
    }
    
    public function toArray($notifiable)
    {
        return [
            'link' => $this->link,
            'message' => $this->subject
        ];
    }
}
















