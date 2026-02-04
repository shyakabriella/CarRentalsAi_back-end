<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class RegistrationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $user,
        public string $plainPassword,
        public bool $isTemporary = true
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        // ✅ Change this if you have a frontend login link
        // Example: https://smartbookingcars.com/login
        $appUrl = config('app.url');
        $loginUrl = rtrim($appUrl, '/') . '/login';

        return (new MailMessage)
            ->subject('Welcome to ' . config('app.name') . ' – Your Login Credentials')
            ->markdown('emails.registration', [
                'user'         => $this->user,
                'plainPassword'=> $this->plainPassword,
                'isTemporary'  => $this->isTemporary,
                'loginUrl'     => $loginUrl,
                'appName'      => config('app.name'),
                'supportEmail' => config('mail.from.address'),
            ]);
    }
}
