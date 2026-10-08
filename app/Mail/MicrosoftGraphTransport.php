<?php

namespace App\Mail;

use App\Models\Setting;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Sends fully-rendered MIME messages through Microsoft Graph.
 *
 * This keeps Laravel's normal Mailable/Notification behaviour (including
 * reply-to headers and future attachments) while using a revocable delegated
 * refresh token instead of storing a Microsoft 365 mailbox password.
 */
class MicrosoftGraphTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private string $refreshToken,
        private readonly string $sender,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $response = $this->sendToGraph($message, $this->accessToken());

        // A cached token can be revoked before its advertised expiry. Refresh
        // once on an authentication failure, then surface the real error.
        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
            $response = $this->sendToGraph($message, $this->accessToken());
        }

        if (! $response->successful()) {
            $code = $response->json('error.code', 'unknown_error');

            throw new TransportException(
                "Microsoft Graph rejected the email (HTTP {$response->status()}, {$code}).",
            );
        }
    }

    private function sendToGraph(SentMessage $message, string $accessToken): Response
    {
        return Http::withToken($accessToken)
            ->acceptJson()
            ->withBody(base64_encode($message->toString()), 'text/plain')
            ->timeout(20)
            ->post('https://graph.microsoft.com/v1.0/me/sendMail');
    }

    private function accessToken(): string
    {
        if ($token = Cache::get($this->tokenCacheKey())) {
            return $token;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 250)
            ->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'scope' => 'openid profile email offline_access Mail.Send',
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful() || ! is_string($response->json('access_token'))) {
            $code = $response->json('error', 'token_error');

            throw new TransportException(
                "Microsoft could not issue an email access token (HTTP {$response->status()}, {$code}).",
            );
        }

        $token = $response->json('access_token');
        if (is_string($rotatedRefreshToken = $response->json('refresh_token'))) {
            $this->refreshToken = $rotatedRefreshToken;
            Setting::set('ms_mail_refresh_token', encrypt($rotatedRefreshToken));
        }
        $ttl = max(60, ((int) $response->json('expires_in', 3600)) - 300);
        Cache::put($this->tokenCacheKey(), $token, now()->addSeconds($ttl));

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return 'microsoft-graph-mail-token:'.hash('sha256', "{$this->tenantId}|{$this->clientId}|".mb_strtolower($this->sender));
    }

    public function __toString(): string
    {
        return 'microsoft-graph';
    }
}
