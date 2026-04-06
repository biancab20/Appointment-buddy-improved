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

    // GET /api/student/bookings
    public function studentBookings(): void
    {
        $this->requireStudent();

        try {
            $scope = strtolower(trim((string)($_GET['scope'] ?? 'upcoming')));
            if (!in_array($scope, ['upcoming', 'history'], true)) {
                throw new \RuntimeException('Invalid scope value.');
            }

            $page = $this->readRequiredIntQuery('page', 1, 1);
            $perPage = $this->readRequiredIntQuery('per_page', 6, 1);
            $perPage = min($perPage, 20);

            $dateFrom = $this->readOptionalDateQuery('date_from');
            $dateTo = $this->readOptionalDateQuery('date_to');

            $result = $this->bookingService->getBookingsForUserPaginated(
                $this->authUserId(),
                $scope,
                $dateFrom,
                $dateTo,
                $page,
                $perPage
            );
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        }

        $bookings = $result['items'] ?? [];
        $total = (int)($result['total'] ?? 0);
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        $this->json([
            'bookings' => $bookings,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
            ],
            'filters' => [
                'scope' => $scope,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    // DELETE /api/student/bookings/{id}
    public function cancelBooking(array $params): void
    {
        $this->requireStudent();

        $bookingId = (int)($params['id'] ?? 0);
        if ($bookingId <= 0) {
            $this->json(['error' => 'Invalid booking id.'], 400);
            return;
        }

        try {
            $result = $this->bookingService->cancelBookingWithPolicyForUser(
                $bookingId,
                $this->authUserId()
            );
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        }

        $this->json([
            'booking_id' => $bookingId,
            'refund_eligible' => (bool)$result['refund_eligible'],
            'message' => (string)$result['message'],
        ]);
    }

    // GET /api/student/bookings/{id}/reschedule-options
    public function rescheduleOptions(array $params): void
    {
        $this->requireStudent();

        $bookingId = (int)($params['id'] ?? 0);
        if ($bookingId <= 0) {
            $this->json(['error' => 'Invalid booking id.'], 400);
            return;
        }

        try {
            $result = $this->bookingService->getRescheduleOptionsForUser(
                $bookingId,
                $this->authUserId()
            );
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        }

        $this->json([
            'booking' => $result['booking'],
            'timeslots' => $result['timeslots'],
        ]);
    }

    // PUT /api/student/bookings/{id}/reschedule
    public function rescheduleBooking(array $params): void
    {
        $this->requireStudent();

        $bookingId = (int)($params['id'] ?? 0);
        if ($bookingId <= 0) {
            $this->json(['error' => 'Invalid booking id.'], 400);
            return;
        }

        $payload = $this->readJsonBody();
        $newTimeslotId = (int)($payload['new_timeslot_id'] ?? 0);
        if ($newTimeslotId <= 0) {
            $this->json(['error' => 'Invalid new timeslot id.'], 400);
            return;
        }

        try {
            $this->bookingService->changePaidBookingTimeslot(
                $bookingId,
                $this->authUserId(),
                $newTimeslotId
            );
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        }

        $this->json([
            'booking_id' => $bookingId,
            'new_timeslot_id' => $newTimeslotId,
            'message' => 'Booking rescheduled successfully.',
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

    private function readRequiredIntQuery(string $key, int $default, int $min): int
    {
        $rawValue = $_GET[$key] ?? null;
        if ($rawValue === null || $rawValue === '') {
            return $default;
        }

        $value = filter_var($rawValue, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min) {
            throw new \RuntimeException("Invalid {$key} value.");
        }

        return (int)$value;
    }

    private function readOptionalDateQuery(string $key): ?string
    {
        $rawValue = trim((string)($_GET[$key] ?? ''));
        if ($rawValue === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawValue)) {
            throw new \RuntimeException("Invalid {$key} value.");
        }

        [$year, $month, $day] = array_map('intval', explode('-', $rawValue));
        if (!checkdate($month, $day, $year)) {
            throw new \RuntimeException("Invalid {$key} value.");
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
