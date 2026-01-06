<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailBase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class VerifyEmail extends VerifyEmailBase
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        // Generate a 6-digit verification code
        $verificationCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store the code in cache with expiration (60 minutes)
        $expirationMinutes = Config::get('auth.verification.expire', 60);
        Cache::put(
            "verification_code_{$notifiable->getKey()}",
            $verificationCode,
            now()->addMinutes($expirationMinutes)
        );

        return (new MailMessage)
            ->subject('Verify Your Email Address - Panabo City ANIMED')
            ->view('emails.verify-email', [
                'verificationCode' => $verificationCode,
                'user' => $notifiable,
                'expirationMinutes' => $expirationMinutes,
            ]);
    }
}














