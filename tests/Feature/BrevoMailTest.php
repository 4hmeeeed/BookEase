<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SystemTestEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/** MAIL_MAILER=brevo sends through Brevo's HTTPS API (InfinityFree blocks SMTP). */
class BrevoMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'brevo',
            'services.brevo.key' => 'xkeysib-test',
            'mail.from' => ['address' => 'bookings@labnasan.test', 'name' => 'Labnasan Resort'],
        ]);
        app('mail.manager')->forgetMailers();
    }

    public function test_mail_is_posted_to_brevo_with_the_api_key(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<abc@brevo>'], 201)]);

        User::factory()->create(['email' => 'ana@example.com', 'name' => 'Ana'])->notify(new SystemTestEmail);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'xkeysib-test')
                && $request['sender']['email'] === 'bookings@labnasan.test'
                && $request['to'][0]['email'] === 'ana@example.com'
                && filled($request['subject'])
                && filled($request['htmlContent']);
        });
    }

    public function test_a_refusal_from_brevo_is_reported(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['code' => 'unauthorized', 'message' => 'Key not found'], 401)]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Key not found');

        User::factory()->create()->notify(new SystemTestEmail);
    }

    public function test_a_missing_key_is_reported(): void
    {
        config(['services.brevo.key' => null]);
        app('mail.manager')->forgetMailers();
        Http::fake();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('BREVO_API_KEY');

        User::factory()->create()->notify(new SystemTestEmail);
    }
}
