<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        /** @var CanResetPassword $notifiable */
        $expires = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Reset your password')
            ->view('emails.password-reset', [
                'url' => $this->resetUrl($notifiable),
                'email' => $notifiable->getEmailForPasswordReset(),
                'expires' => $expires,
                'appName' => config('app.name'),
            ]);
    }
}
