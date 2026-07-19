<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

// Base for all Hub notifications: routes to push and/or email according to the
// recipient's NotificationPref (global toggles + per-category opt-outs), with
// email acting as the fallback channel (SPEC.md §22).
abstract class HubNotification extends Notification
{
    use Queueable;

    abstract public function category(): string;

    abstract public function title(): string;

    abstract public function body(): string;

    public function url(): string
    {
        return '/';
    }

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $pref = $notifiable->pref();

        if (! $pref->categoryEnabled($this->category())) {
            return [];
        }

        $channels = [];

        if ($pref->push_enabled && config('webpush.vapid.public_key') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        if ($pref->email_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->icon('/icon.svg')
            ->data(['url' => $this->url()]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Hello '.explode(' ', $notifiable->name)[0].',')
            ->line($this->body())
            ->action('Open Areterra Hub', url($this->url()))
            ->salutation('— Areterra Hub');
    }
}
