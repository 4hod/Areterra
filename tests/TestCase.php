<?php

namespace Tests;

use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    protected function assertNotificationQueuedTo(object $notifiable, string $notificationClass): void
    {
        Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job) =>
            $job->notification instanceof $notificationClass
            && $job->notifiables->contains(fn ($recipient) => $recipient::class === $notifiable::class && $recipient->getKey() === $notifiable->getKey())
        );
    }

    protected function assertNotificationNotQueuedTo(object $notifiable, string $notificationClass): void
    {
        Queue::assertNotPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job) =>
            $job->notification instanceof $notificationClass
            && $job->notifiables->contains(fn ($recipient) => $recipient::class === $notifiable::class && $recipient->getKey() === $notifiable->getKey())
        );
    }

    protected function assertUniqueNotificationCount(string $notificationClass, int $expected): void
    {
        $ids = Queue::pushed(SendQueuedNotifications::class)
            ->filter(fn (SendQueuedNotifications $job) => $job->notification instanceof $notificationClass)
            ->pluck('notification.id')
            ->unique();

        $this->assertCount($expected, $ids);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests assert Inertia responses and do not execute the compiled
        // frontend. Keeping Vite disabled here makes the PHP suite independent
        // from the separate frontend build job in CI.
        $this->withoutVite();
    }
}
