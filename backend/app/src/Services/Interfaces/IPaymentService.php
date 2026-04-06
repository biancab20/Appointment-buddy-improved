<?php

namespace App\Services\Interfaces;

interface IPaymentService
{
    /**
     * @return array{checkout_url:string,session_id:string,transaction_id:int}
     */
    public function createCheckoutSessionForTimeslot(
        int $studentId,
        int $timeslotId,
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): array;

    public function handleStripeWebhook(string $payload, string $signatureHeader): void;
}
