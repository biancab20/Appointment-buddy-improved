<?php

namespace App\Controllers\Api;

use App\Services\Interfaces\IBookingService;
use App\Services\BookingService;
use App\Services\Interfaces\IPaymentService;
use App\Services\PaymentService;

class BookingApiController extends ApiBaseController
{
    private IBookingService $bookingService;
    private IPaymentService $paymentService;

    public function __construct()
    {
        $this->bookingService = new BookingService();
        $this->paymentService = new PaymentService();
    }

    // GET /api/admin/bookings/paid
    public function paid(): void
    {
        $this->requireAdmin();

        $all = $this->bookingService->getAllBookings();
        $paid = array_values(array_filter($all, fn($b) => ($b['status'] ?? '') === 'paid'));

        $this->json([
            'paid' => $paid
        ]);
    }

    // GET /api/student/bookings/upcoming-count
    public function upcomingCount(): void
    {
        $this->requireStudent();

        $count = $this->bookingService->countUpcomingForUser($this->authUserId());

        $this->json([
            'upcoming_count' => $count,
        ]);
    }

    // POST /api/student/bookings/checkout-session
    public function createCheckoutSession(): void
    {
        $this->requireStudent();

        $payload = $this->readJsonBody();
        $timeslotId = (int)($payload['timeslot_id'] ?? 0);
        $successUrl = isset($payload['success_url']) ? (string)$payload['success_url'] : null;
        $cancelUrl = isset($payload['cancel_url']) ? (string)$payload['cancel_url'] : null;

        if ($timeslotId <= 0) {
            $this->json(['error' => 'Invalid timeslot id.'], 400);
            return;
        }

        try {
            $session = $this->paymentService->createCheckoutSessionForTimeslot(
                $this->authUserId(),
                $timeslotId,
                $successUrl,
                $cancelUrl
            );
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        } catch (\Throwable $e) {
            $this->json(['error' => 'Unable to start payment checkout.'], 500);
            return;
        }

        $this->json($session, 201);
    }

    // POST /api/stripe/webhook
    public function stripeWebhook(): void
    {
        $payload = file_get_contents('php://input');
        if ($payload === false) {
            $this->json(['error' => 'Invalid request body.'], 400);
            return;
        }

        $signatureHeader = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

        try {
            $this->paymentService->handleStripeWebhook($payload, $signatureHeader);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        } catch (\Throwable $e) {
            $this->json(['error' => 'Stripe webhook processing failed.'], 500);
            return;
        }

        $this->json(['received' => true], 200);
    }
}
