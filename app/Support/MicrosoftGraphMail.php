<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

final class MicrosoftGraphMail
{
    /** @return array{tenant_id:?string,client_id:?string,client_secret:?string,refresh_token:?string,sender:?string} */
    public static function configuration(array $mailer = []): array
    {
        return [
            'tenant_id' => self::value($mailer['tenant_id'] ?? config('services.microsoft.tenant_id') ?? Setting::get('ms_tenant_id')),
            'client_id' => self::value($mailer['client_id'] ?? config('services.microsoft.client_id') ?? Setting::get('ms_client_id')),
            'client_secret' => self::secret($mailer['client_secret'] ?? config('services.microsoft.client_secret'), 'ms_client_secret'),
            'refresh_token' => self::secret(null, 'ms_mail_refresh_token'),
            'sender' => self::value(Setting::get('ms_mail_sender', $mailer['sender'] ?? config('mail.from.address'))),
        ];
    }

    public static function ready(array $mailer = []): bool
    {
        foreach (self::configuration($mailer) as $value) {
            if ($value === null) {
                return false;
            }
        }

        return true;
    }

    public static function tokenCacheKey(?string $tenantId, ?string $clientId, ?string $sender): string
    {
        return 'microsoft-graph-mail-token:'.hash('sha256', implode('|', [
            $tenantId ?? '', $clientId ?? '', mb_strtolower($sender ?? ''),
        ]));
    }

    public static function forgetCachedToken(?string $sender = null): void
    {
        $configuration = self::configuration();
        Cache::forget(self::tokenCacheKey(
            $configuration['tenant_id'],
            $configuration['client_id'],
            $sender ?? $configuration['sender'],
        ));
    }

    private static function secret(mixed $configured, string $setting): ?string
    {
        if ($value = self::value($configured)) {
            return $value;
        }

        $stored = Setting::get($setting);
        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return self::value(decrypt($stored));
        } catch (\Throwable) {
            return null;
        }
    }

    private static function value(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
