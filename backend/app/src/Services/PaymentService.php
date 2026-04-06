<?php

namespace App\Services;

use App\Repositories\BookingRepository;
use App\Repositories\Interfaces\IBookingRepository;
use App\Repositories\Interfaces\IServiceRepository;
use App\Repositories\Interfaces\ITimeslotRepository;
use App\Repositories\Interfaces\ITransactionRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\TimeslotRepository;
use App\Repositories\TransactionRepository;
use App\Security\StripeService;
use App\Services\Interfaces\IBookingService;
use App\Services\Interfaces\IPaymentService;

class PaymentService implements IPaymentService
{
    private ITimeslotRepository $timeslotRepository;
    private IServiceRepository $serviceRepository;
    private IBookingRepository $bookingRepository;
    private ITransactionRepository $transactionRepository;
    private IBookingService $bookingService;
    private ?StripeService $stripeService = null;
    private string $defaultSuccessUrl;
    private string $defaultCancelUrl;
    private string $currency;

    public function __construct()
    {
        $this->timeslotRepository = new TimeslotRepository();
        $this->serviceRepository = new ServiceRepository();
        $this->bookingRepository = new BookingRepository();
        $this->transactionRepository = new TransactionRepository();
        $this->bookingService = new BookingService();

        $this->defaultSuccessUrl = trim((string)(getenv('STRIPE_SUCCESS_URL') ?: ''));
        $this->defaultCancelUrl = trim((string)(getenv('STRIPE_CANCEL_URL') ?: ''));
        $this->currency = strtolower(trim((string)(getenv('STRIPE_CURRENCY') ?: 'eur')));
    }

    public function createCheckoutSessionForTimeslot(
        int $studentId,
        int $timeslotId,
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): array {
        if ($studentId <= 0) {
            throw new \RuntimeException('Invalid student id.');
        }

        if ($timeslotId <= 0) {
            throw new \RuntimeException('Invalid timeslot id.');
        }

        $timeslot = $this->timeslotRepository->find($timeslotId);
        if (!$timeslot || !$timeslot->isActive) {
            throw new \RuntimeException('This timeslot is unavailable.');
        }

        $startAt = new \DateTimeImmutable($timeslot->startTime);
        if ($startAt <= new \DateTimeImmutable('now')) {
            throw new \RuntimeException('Past timeslots cannot be booked.');
        }

        $service = $this->serviceRepository->find($timeslot->serviceId);
        if (!$service || !$service->isActive) {
            throw new \RuntimeException('This service is unavailable.');
        }

        if ($this->bookingRepository->existsForUserAndTimeslot($studentId, $timeslotId)) {
            throw new \RuntimeException('You already booked this timeslot.');
        }

        if ($this->bookingRepository->existsActiveForTimeslot($timeslotId)) {
            throw new \RuntimeException('This timeslot is already booked.');
        }

        $amount = (float)$service->price;
        if ($amount <= 0) {
            throw new \RuntimeException('Service price is invalid.');
        }

        $resolvedSuccessUrl = $this->resolveCheckoutUrl(
            $successUrl,
            $this->defaultSuccessUrl,
            'STRIPE_SUCCESS_URL'
        );
        $resolvedCancelUrl = $this->resolveCheckoutUrl(
            $cancelUrl,
            $this->defaultCancelUrl,
            'STRIPE_CANCEL_URL'
        );

        $session = $this->stripe()->createCheckoutSession([
            'mode' => 'payment',
            'success_url' => $resolvedSuccessUrl,
            'cancel_url' => $resolvedCancelUrl,
            'client_reference_id' => 'student:' . $studentId,
            'metadata' => [
                'student_id' => (string)$studentId,
                'timeslot_id' => (string)$timeslotId,
                'service_id' => (string)$service->id,
            ],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $this->currency,
                    'unit_amount' => (int)round($amount * 100),
                    'product_data' => [
                        'name' => $service->title,
                        'description' => sprintf(
                            'Tutor: %s | %s - %s',
                            $service->tutorName ?: 'Unknown tutor',
                            $timeslot->startTime,
                            $timeslot->endTime
                        ),
                    ],
                ],
            ]],
        ]);

        $sessionId = (string)($session['id'] ?? '');
        $checkoutUrl = (string)($session['url'] ?? '');
        $paymentIntent = isset($session['payment_intent']) ? (string)$session['payment_intent'] : null;

        if ($sessionId === '' || $checkoutUrl === '') {
            throw new \RuntimeException('Could not create Stripe checkout session.');
        }

        $transactionId = $this->transactionRepository->createPending(
            studentId: $studentId,
            tutorId: (int)$service->tutorId,
            serviceId: (int)$service->id,
            timeslotId: $timeslotId,
            providerSessionId: $sessionId,
            providerPaymentIntentId: $paymentIntent,
            amount: $amount,
            currency: $this->currency
        );

        return [
            'checkout_url' => $checkoutUrl,
            'session_id' => $sessionId,
            'transaction_id' => $transactionId,
        ];
    }

    public function handleStripeWebhook(string $payload, string $signatureHeader): void
    {
        $event = $this->stripe()->constructWebhookEvent($payload, $signatureHeader);
        $eventType = (string)($event['type'] ?? '');
        $object = $event['data']['object'] ?? null;

        if (!is_array($object)) {
            return;
        }

        if ($eventType === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($object);
            return;
        }

        if ($eventType === 'checkout.session.expired') {
            $this->handleCheckoutExpired($object);
            return;
        }

        if ($eventType === 'checkout.session.async_payment_failed') {
            $this->handleCheckoutFailed($object);
        }
    }

    /**
     * @param array<string, mixed> $session
     */
    private function handleCheckoutCompleted(array $session): void
    {
        $sessionId = (string)($session['id'] ?? '');
        if ($sessionId === '') {
            return;
        }

        $transaction = $this->transactionRepository->findBySessionId($sessionId);
        if (!$transaction) {
            return;
        }

        $status = (string)($transaction['status'] ?? '');
        if ($status === 'paid') {
            return;
        }

        $paymentStatus = (string)($session['payment_status'] ?? '');
        if ($paymentStatus !== 'paid') {
            $this->transactionRepository->markFailed((int)$transaction['id'], 'Payment was not completed.');
            return;
        }

        $paymentIntent = isset($session['payment_intent']) ? (string)$session['payment_intent'] : null;

        try {
            $bookingId = $this->bookingService->requestBooking(
                (int)$transaction['student_id'],
                (int)$transaction['timeslot_id']
            );
        } catch (\RuntimeException $e) {
            $this->transactionRepository->markFailed((int)$transaction['id'], $e->getMessage());
            return;
        }

        $this->transactionRepository->markPaid(
            (int)$transaction['id'],
            $bookingId,
            $paymentIntent
        );
    }

    /**
     * @param array<string, mixed> $session
     */
    private function handleCheckoutExpired(array $session): void
    {
        $sessionId = (string)($session['id'] ?? '');
        if ($sessionId === '') {
            return;
        }

        $transaction = $this->transactionRepository->findBySessionId($sessionId);
        if (!$transaction) {
            return;
        }

        $this->transactionRepository->markCancelled((int)$transaction['id'], 'Checkout session expired.');
    }

    /**
     * @param array<string, mixed> $session
     */
    private function handleCheckoutFailed(array $session): void
    {
        $sessionId = (string)($session['id'] ?? '');
        if ($sessionId === '') {
            return;
        }

        $transaction = $this->transactionRepository->findBySessionId($sessionId);
        if (!$transaction) {
            return;
        }

        $this->transactionRepository->markFailed((int)$transaction['id'], 'Stripe payment failed.');
    }

    private function stripe(): StripeService
    {
        if ($this->stripeService === null) {
            $this->stripeService = new StripeService();
        }

        return $this->stripeService;
    }

    private function resolveCheckoutUrl(?string $candidate, string $fallback, string $envName): string
    {
        $candidate = trim((string)$candidate);
        if ($candidate !== '') {
            if (!$this->isAbsoluteHttpUrl($candidate)) {
                throw new \RuntimeException('Invalid redirect url.');
            }
            return $candidate;
        }

        if ($fallback === '' || !$this->isAbsoluteHttpUrl($fallback)) {
            throw new \RuntimeException("{$envName} is not configured correctly.");
        }

        return $fallback;
    }

    private function isAbsoluteHttpUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }
}
