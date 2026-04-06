<?php

namespace App\Services;

use App\Models\BookingModel;
use App\Models\TimeslotModel;
use App\Repositories\BookingRepository;
use App\Repositories\Interfaces\IBookingRepository;
use App\Repositories\Interfaces\IServiceRepository;
use App\Repositories\Interfaces\ITimeslotRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\TimeslotRepository;
use App\Services\Interfaces\IBookingService;

class BookingService implements IBookingService
{
    private const POLICY_WINDOW_SECONDS = 48 * 60 * 60;

    private IBookingRepository $bookingRepository;
    private ITimeslotRepository $timeslotRepository;
    private IServiceRepository $serviceRepository;

    public function __construct()
    {
        $this->bookingRepository = new BookingRepository();
        $this->timeslotRepository = new TimeslotRepository();
        $this->serviceRepository = new ServiceRepository();
    }

    public function requestBooking(int $studentId, int $timeslotId): int
    {
        $timeslot = $this->timeslotRepository->find($timeslotId);
        if (!$timeslot || !$timeslot->isActive) {
            throw new \RuntimeException("This timeslot is unavailable.");
        }

        $start = new \DateTimeImmutable($timeslot->startTime);
        if ($start <= new \DateTimeImmutable('now')) {
            throw new \RuntimeException("Past timeslots cannot be booked.");
        }

        $service = $this->serviceRepository->find($timeslot->serviceId);
        if (!$service || !$service->isActive) {
            throw new \RuntimeException("This service is unavailable.");
        }

        // Prevent same student booking same slot twice
        if ($this->bookingRepository->existsForUserAndTimeslot($studentId, $timeslotId)) {
            throw new \RuntimeException("You already booked this timeslot.");
        }

        // Prevent two students booking same timeslot
        if ($this->bookingRepository->existsActiveForTimeslot($timeslotId)) {
            throw new \RuntimeException("This timeslot is already booked.");
        }

        $booking = new BookingModel(
            id: null,
            studentId: $studentId,
            timeslotId: $timeslotId,
            priceAtBooking: (float)$service->price,
            status: BookingModel::STATUS_PAID,
            createdAt: null
        );

        return $this->bookingRepository->create($booking);
    }

    public function getBookingsForUser(int $userId): array
    {
        return $this->bookingRepository->forUser($userId);
    }

    public function getBookingsForUserPaginated(
        int $userId,
        string $scope,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage
    ): array {
        if ($userId <= 0) {
            throw new \RuntimeException('Invalid user id.');
        }

        if (!in_array($scope, ['upcoming', 'history'], true)) {
            throw new \RuntimeException('Invalid scope value.');
        }

        if ($page <= 0) {
            throw new \RuntimeException('Page must be greater than 0.');
        }

        if ($perPage <= 0) {
            throw new \RuntimeException('Per-page must be greater than 0.');
        }

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            throw new \RuntimeException('Date from cannot be greater than date to.');
        }

        return $this->bookingRepository->getForStudentPaginated(
            $userId,
            $scope,
            $dateFrom,
            $dateTo,
            $page,
            $perPage
        );
    }

    public function getAllBookings(): array
    {
        return $this->bookingRepository->getAll();
    }

    public function cancelBookingForUser(int $bookingId, int $userId): bool
    {
        $this->cancelBookingWithPolicyForUser($bookingId, $userId);
        return true;
    }

    public function cancelBookingWithPolicyForUser(int $bookingId, int $userId): array
    {
        $booking = $this->bookingRepository->findByIdForUser($bookingId, $userId);

        if (!$booking) {
            throw new \RuntimeException("Booking not found.");
        }

        if (($booking['status'] ?? '') === BookingModel::STATUS_CANCELLED) {
            throw new \RuntimeException("This booking is already cancelled.");
        }

        $start = $this->parseBookingStart($booking);
        $now = new \DateTimeImmutable('now');

        if ($start <= $now) {
            throw new \RuntimeException("Past appointments cannot be cancelled.");
        }

        $refundEligible = $this->secondsUntil($start, $now) >= self::POLICY_WINDOW_SECONDS;

        $cancelled = $this->bookingRepository->cancelForUser($bookingId, $userId);
        if (!$cancelled) {
            throw new \RuntimeException("Could not cancel booking.");
        }

        if ($refundEligible) {
            return [
                'refund_eligible' => true,
                'message' => 'Booking cancelled. You are eligible for a refund. The amount will be returned within a few working days.',
            ];
        }

        return [
            'refund_eligible' => false,
            'message' => 'Booking cancelled. Because this cancellation was made less than 48 hours before the session, no refund will be issued.',
        ];
    }

    public function updateBookingStatus(int $bookingId, string $status): bool
    {
        return $this->bookingRepository->updateStatus($bookingId, $status);
    }

    public function countUpcomingForUser(int $userId): int
    {
        return $this->bookingRepository->countUpcomingForUser($userId);
    }

    public function findByIdForUser(int $bookingId, int $userId): ?array
    {
        return $this->bookingRepository->findByIdForUser($bookingId, $userId);
    }

    public function updateTimeslotForPaid(int $bookingId, int $userId, int $newTimeslotId): bool
    {
        return $this->bookingRepository->updateTimeslotForPaid($bookingId, $userId, $newTimeslotId);
    }

    public function changePaidBookingTimeslot(int $bookingId, int $userId, int $newTimeslotId): void
    {
        $booking = $this->bookingRepository->findByIdForUser($bookingId, $userId);
        if (!$booking) {
            throw new \RuntimeException("Booking not found.");
        }

        if ($booking['status'] !== BookingModel::STATUS_PAID) {
            throw new \RuntimeException("Only paid bookings can be changed.");
        }

        $start = $this->parseBookingStart($booking);
        $now = new \DateTimeImmutable('now');
        if ($start <= $now) {
            throw new \RuntimeException("Past appointments cannot be rescheduled.");
        }

        if ($this->secondsUntil($start, $now) < self::POLICY_WINDOW_SECONDS) {
            throw new \RuntimeException(
                "Rescheduling is only allowed more than 48 hours before the session. The timeframe is too short to notify the tutor or fill this timeslot. You can still cancel if you cannot attend."
            );
        }

        $service = $this->serviceRepository->find((int)$booking['service_id']);
        if (!$service) {
            throw new \RuntimeException("Service not found.");
        }

        $priceAtBooking = (float)($booking['price_at_booking'] ?? 0);
        $currentPrice = (float)$service->price;
        if ($currentPrice > ($priceAtBooking + 0.00001)) {
            throw new \RuntimeException(
                "This service price has increased since you booked. Rescheduling is not available for this booking."
            );
        }

        if ((int) $booking['timeslot_id'] === $newTimeslotId) {
            throw new \RuntimeException("Please select a different timeslot.");
        }

        $newTimeslot = $this->timeslotRepository->find($newTimeslotId);
        if (!$newTimeslot || !$newTimeslot->isActive) {
            throw new \RuntimeException("That timeslot is no longer available.");
        }

        $newStart = new \DateTimeImmutable($newTimeslot->startTime);
        if ($newStart <= $now) {
            throw new \RuntimeException("Past timeslots cannot be selected.");
        }

        if ((int)$newTimeslot->serviceId !== (int)$booking['service_id']) {
            throw new \RuntimeException("Invalid timeslot selection for this service.");
        }

        // block already booked timeslot
        if ($this->bookingRepository->existsActiveForTimeslot($newTimeslotId)) {
            throw new \RuntimeException("That timeslot is no longer available.");
        }

        $ok = $this->bookingRepository->updateTimeslotForPaid($bookingId, $userId, $newTimeslotId);
        if (!$ok) {
            throw new \RuntimeException("Could not change booking timeslot.");
        }
    }

    public function getRescheduleOptionsForUser(int $bookingId, int $userId): array
    {
        $booking = $this->bookingRepository->findByIdForUser($bookingId, $userId);
        if (!$booking) {
            throw new \RuntimeException("Booking not found.");
        }

        if (($booking['status'] ?? '') !== BookingModel::STATUS_PAID) {
            throw new \RuntimeException("Only paid bookings can be rescheduled.");
        }

        $start = $this->parseBookingStart($booking);
        $now = new \DateTimeImmutable('now');
        if ($start <= $now) {
            throw new \RuntimeException("Past appointments cannot be rescheduled.");
        }

        if ($this->secondsUntil($start, $now) < self::POLICY_WINDOW_SECONDS) {
            throw new \RuntimeException(
                "Rescheduling is only allowed more than 48 hours before the session. The timeframe is too short to notify the tutor or fill this timeslot. You can still cancel if you cannot attend."
            );
        }

        $service = $this->serviceRepository->find((int)$booking['service_id']);
        if (!$service) {
            throw new \RuntimeException("Service not found.");
        }

        $priceAtBooking = (float)($booking['price_at_booking'] ?? 0);
        $currentPrice = (float)$service->price;
        if ($currentPrice > ($priceAtBooking + 0.00001)) {
            throw new \RuntimeException(
                "This service price has increased since you booked. Rescheduling is not available for this booking."
            );
        }

        $timeslots = $this->timeslotRepository->getUpcomingForService((int)$booking['service_id']);
        $timeslots = array_values(array_filter(
            $timeslots,
            fn(TimeslotModel $t) => (int)$t->id !== (int)$booking['timeslot_id']
        ));

        return [
            'booking' => $booking,
            'timeslots' => array_map(fn(TimeslotModel $t) => $t->toArray(), $timeslots),
        ];
    }

    public function countPaidBookings(): int
    {
        return $this->bookingRepository->countPaid();
    }

    private function parseBookingStart(array $booking): \DateTimeImmutable
    {
        return new \DateTimeImmutable((string)($booking['start_time'] ?? 'now'));
    }

    private function secondsUntil(\DateTimeImmutable $start, \DateTimeImmutable $now): int
    {
        return $start->getTimestamp() - $now->getTimestamp();
    }
}
