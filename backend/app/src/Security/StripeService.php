<?php

namespace App\Security;

use Stripe\StripeClient;
use Stripe\Webhook;

class StripeService
{
    private StripeClient $client;
    private string $webhookSecret;

    public function __construct()
    {
        $secretKey = trim((string)(getenv('STRIPE_SECRET_KEY') ?: ''));
        if ($secretKey === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        $this->client = new StripeClient($secretKey);
        $this->webhookSecret = trim((string)(getenv('STRIPE_WEBHOOK_SECRET') ?: ''));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function createCheckoutSession(array $params): array
    {
        $session = $this->client->checkout->sessions->create($params);
        return $session->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function constructWebhookEvent(string $payload, string $signatureHeader): array
    {
        if ($this->webhookSecret === '') {
            throw new \RuntimeException('Stripe webhook secret is not configured.');
        }

        $signatureHeader = trim($signatureHeader);
        if ($signatureHeader === '') {
            throw new \RuntimeException('Missing Stripe signature header.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
        } catch (\UnexpectedValueException $e) {
            throw new \RuntimeException('Invalid Stripe payload.');
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            throw new \RuntimeException('Invalid Stripe signature.');
        }

        return $event->toArray();
    }
}
