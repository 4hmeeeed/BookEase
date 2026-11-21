<?php

namespace App\Mail;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * Sends mail through Brevo's HTTPS API (https://api.brevo.com/v3/smtp/email).
 *
 * Free hosts such as InfinityFree block or throttle outgoing SMTP ports, but
 * allow ordinary HTTPS requests, so this works where MAIL_MAILER=smtp doesn't.
 * Brevo's free plan sends 300 emails a day from a verified sender address.
 */
class BrevoTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(private readonly ?string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (blank($this->apiKey)) {
            throw new TransportException('BREVO_API_KEY is not set.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();

        $payload = array_filter([
            'sender' => $this->address($envelope->getSender()),
            'to' => array_map($this->address(...), $email->getTo()),
            'cc' => array_map($this->address(...), $email->getCc()),
            'bcc' => array_map($this->address(...), $email->getBcc()),
            'replyTo' => ($reply = $email->getReplyTo()[0] ?? null) ? $this->address($reply) : null,
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
            'textContent' => $email->getTextBody(),
            'attachment' => array_map(fn ($part) => [
                'name' => $part->getFilename() ?? 'attachment',
                'content' => base64_encode($part->getBody()),
            ], $email->getAttachments()),
        ], fn ($value) => $value !== null && $value !== []);

        try {
            $response = Http::withHeaders(['api-key' => $this->apiKey, 'accept' => 'application/json'])
                ->timeout(15)
                ->post(self::ENDPOINT, $payload);
        } catch (ConnectionException $e) {
            throw new TransportException('Could not reach Brevo: '.$e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new TransportException(sprintf('Brevo refused the message (%d): %s', $response->status(), $response->json('message') ?? $response->body()));
        }

        if ($id = $response->json('messageId')) {
            $message->setMessageId((string) $id);
        }
    }

    /**
     * @return array{email: string, name?: string}
     */
    private function address(Address $address): array
    {
        return array_filter(['email' => $address->getAddress(), 'name' => $address->getName()]);
    }

    public function __toString(): string
    {
        return 'brevo+api://default';
    }
}
