<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class DatabaseNotification extends Notification
{
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
        return ['database'];
    }
    
    public function toArray($notifiable)
    {
        return [
            'link' => $this->link,
            'subject' => $this->subject,
            'message' => $this->message
        ];
    }
}

