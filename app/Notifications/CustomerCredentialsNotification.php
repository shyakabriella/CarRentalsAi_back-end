<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerCredentialsNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $user,
        public Customer $customer,
        public string $plainPassword,
        public bool $isTemporary = true
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(
            config('app.frontend_url') ?: config('app.url'),
            '/'
        );

        $loginUrl = $frontendUrl ? $frontendUrl . '/login' : url('/login');

        return (new MailMessage)
            ->subject('Your Customer Account Credentials')
            ->view('emails.customer-credentials', [
                'user'          => $this->user,
                'customer'      => $this->customer,
                'plainPassword' => $this->plainPassword,
                'isTemporary'   => $this->isTemporary,
                'loginUrl'      => $loginUrl,
                'appName'       => config('app.name', 'Application'),
            ]);
    }
}