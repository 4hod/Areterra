<?php

namespace App\Notifications;

use App\Mail\BrandedEmail;
use App\Models\User;
use Illuminate\Bus\Queueable;
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

        // In-app history is always kept — the category toggles below only
        // govern whether push/email are also sent, per SPEC.md's
        // Notification Preferences page ("push on/off", "email on/off",
        // per-category toggles). Losing in-Hub history because someone
        // muted push for a category would be a worse outcome than just
        // muting the external channels.
        $channels = ['database'];

        $pref = $notifiable->pref();

        if (! $pref->categoryEnabled($this->category())) {
            return $channels;
        }

        if ($pref->push_enabled && config('webpush.vapid.public_key') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        if ($pref->email_enabled) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
        ];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title())
            ->body($this->body())
            ->icon('/icon.svg')
            ->data(['url' => $this->url()]);
    }

    public function toMail(object $notifiable): BrandedEmail
    {
        $firstName = explode(' ', $notifiable->name)[0];
        $body = "Hello {$firstName},\n\n{$this->body()}\n\nOpen Areterra Hub: ".url($this->url())."\n\n— Areterra Hub";

        // A Mailable carries no recipient of its own — unlike a MailMessage,
        // Laravel will not address it for you. Without this the whole
        // notification throws "An email must have a To header", which took
        // down welfare concerns and product orders the moment a manager
        // existed to notify.
        return (new BrandedEmail($this->title(), $body))->to($notifiable->email);
    }
}
