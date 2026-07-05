<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your PlayerSaloons password')
            ->view('emails.auth.reset-password', [
                'user' => $notifiable,
                'url' => $this->resetUrl($notifiable),
                'logoUrl' => asset('icon-192.png'),
                'appName' => config('app.name', 'PlayerSaloons'),
                'expiresIn' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]);
    }
}
