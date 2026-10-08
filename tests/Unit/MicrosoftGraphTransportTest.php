<?php

namespace Tests\Unit;

use App\Mail\MicrosoftGraphTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MicrosoftGraphTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_it_uses_client_credentials_and_sends_the_rendered_mime_message(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'graph-access-token',
                'expires_in' => 3600,
            ]),
            'graph.microsoft.com/*' => Http::response(null, 202),
        ]);

        $transport = new MicrosoftGraphTransport(
            tenantId: 'tenant-id',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            sender: 'team@areterra.co.uk',
        );

        $transport->send(
            (new Email)
                ->from('team@areterra.co.uk')
                ->to('recipient@example.com')
                ->subject('Areterra email test')
                ->text('The secure Graph mail transport works.'),
        );

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), '/oauth2/v2.0/token')
                && $request['grant_type'] === 'client_credentials'
                && $request['scope'] === 'https://graph.microsoft.com/.default';
        });

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://graph.microsoft.com/v1.0/users/team%40areterra.co.uk/sendMail') {
                return false;
            }

            $mime = base64_decode($request->body(), true);

            return $request->hasHeader('Authorization', 'Bearer graph-access-token')
                && is_string($mime)
                && str_contains($mime, 'Subject: Areterra email test')
                && str_contains($mime, 'recipient@example.com')
                && str_contains($mime, 'The secure Graph mail transport works.');
        });
    }

    public function test_it_reuses_a_cached_access_token(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'cached-token',
                'expires_in' => 3600,
            ]),
            'graph.microsoft.com/*' => Http::response(null, 202),
        ]);

        $transport = new MicrosoftGraphTransport(
            tenantId: 'tenant-id',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            sender: 'team@areterra.co.uk',
        );

        foreach (['First', 'Second'] as $subject) {
            $transport->send(
                (new Email)
                    ->from('team@areterra.co.uk')
                    ->to('recipient@example.com')
                    ->subject($subject)
                    ->text('Test'),
            );
        }

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/oauth2/v2.0/token'));
    }
}
