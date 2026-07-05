<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your PlayerSaloons email')
            ->view('emails.auth.verify-email', [
                'user' => $notifiable,
                'url' => $this->verificationUrl($notifiable),
                'logoUrl' => asset('icon-192.png'),
                'appName' => config('app.name', 'PlayerSaloons'),
            ]);
    }
}
