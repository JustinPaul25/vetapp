<?php

namespace App\Mail\Transport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class BrevoTransport extends AbstractTransport
{
    protected string $apiKey;
    protected string $url;

    public function __construct(string $apiKey)
    {
        parent::__construct();
        $this->apiKey = $apiKey;
        $this->url = 'https://api.brevo.com/v3/smtp/email';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        
        $from = $email->getFrom()[0];
        $payload = [
            'sender' => [
                'name' => $from->getName() ?: config('mail.from.name'),
                'email' => $from->getAddress(),
            ],
            'to' => array_map(function ($address) {
                return [
                    'email' => $address->getAddress(),
                    'name' => $address->getName() ?: '',
                ];
            }, iterator_to_array($email->getTo())),
            'subject' => $email->getSubject() ?: '',
        ];

        // Handle CC
        if (!empty($email->getCc())) {
            $payload['cc'] = array_map(function ($address) {
                return [
                    'email' => $address->getAddress(),
                    'name' => $address->getName() ?: '',
                ];
            }, iterator_to_array($email->getCc()));
        }

        // Handle BCC
        if (!empty($email->getBcc())) {
            $payload['bcc'] = array_map(function ($address) {
                return [
                    'email' => $address->getAddress(),
                    'name' => $address->getName() ?: '',
                ];
            }, iterator_to_array($email->getBcc()));
        }

        // Handle reply-to
        if (!empty($email->getReplyTo())) {
            $payload['replyTo'] = [
                'email' => $email->getReplyTo()[0]->getAddress(),
                'name' => $email->getReplyTo()[0]->getName() ?: '',
            ];
        }

        // Handle HTML and text content
        $htmlBody = $email->getHtmlBody();
        $textBody = $email->getTextBody();

        if ($htmlBody) {
            $payload['htmlContent'] = $htmlBody;
        }

        if ($textBody) {
            $payload['textContent'] = $textBody;
        } elseif ($htmlBody) {
            // If only HTML body exists, convert to text
            $payload['textContent'] = strip_tags($htmlBody);
        }

        // Handle attachments
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $contentDisposition = $headers->getHeaderBody('Content-Disposition');
            
            // Extract filename from Content-Disposition header
            $filename = 'attachment';
            if ($contentDisposition && preg_match('/filename[^;=\n]*=(([\'"]).*?\2|[^;\n]*)/', $contentDisposition, $matches)) {
                $filename = trim($matches[1], ' \'"');
            } else {
                $filename = $headers->getHeaderBody('Content-Name') ?: 'attachment';
            }

            $attachments[] = [
                'name' => $filename,
                'content' => base64_encode($attachment->getBody()),
            ];
        }

        if (!empty($attachments)) {
            $payload['attachment'] = $attachments;
        }

        // Send the request
        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($this->url, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException(
                sprintf('Brevo API error: %s - %s', $response->status(), $response->body())
            );
        }
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}

