<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientEmailNotification extends Notification
{

    public $details;
    
    public function __construct($details)
    {
        $this->details = $details;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject($this->details['subject'])
                    ->view('emails.notification', [
                        'subject' => $this->details['subject'],
                        'content' => new \Illuminate\Support\HtmlString($this->details['body']),
                    ]);
    }
}

















