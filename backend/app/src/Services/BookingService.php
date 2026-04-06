<?php

namespace App\Services;

use App\Models\BookingModel;
use App\Repositories\BookingRepository;
use App\Repositories\Interfaces\IBookingRepository;
use App\Repositories\Interfaces\IServiceRepository;
use App\Repositories\Interfaces\ITimeslotRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\TimeslotRepository;
use App\Services\Interfaces\IBookingService;

class BookingService implements IBookingService
{
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
        $booking = $this->bookingRepository->findByIdForUser($bookingId, $userId);

        if (!$booking) {
            throw new \RuntimeException("Booking not found.");
        }

        if ($booking['status'] === BookingModel::STATUS_CANCELLED) {
            throw new \RuntimeException("This booking is already cancelled.");
        }

        $start = new \DateTimeImmutable($booking['start_time']);
        $now = new \DateTimeImmutable('now');

        if ($start <= $now) {
            throw new \RuntimeException("Past appointments cannot be cancelled.");
        }

        return $this->bookingRepository->cancelForUser($bookingId, $userId);
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

        if ((int) $booking['timeslot_id'] === $newTimeslotId) {
            throw new \RuntimeException("Please select a different timeslot.");
        }

        // block already booked timeslot
        if ($this->bookingRepository->existsActiveForTimeslot($newTimeslotId)) {
            throw new \RuntimeException("That timeslot is no longer available.");
        }

        // validate new timeslot belongs to same service
        $newServiceId = $this->timeslotRepository->getServiceIdForTimeslot($newTimeslotId);
        if ($newServiceId === null) {
            throw new \RuntimeException("Selected timeslot does not exist.");
        }

        if ((int) $newServiceId !== (int) $booking['service_id']) {
            throw new \RuntimeException("Invalid timeslot selection for this service.");
        }

        $ok = $this->bookingRepository->updateTimeslotForPaid($bookingId, $userId, $newTimeslotId);
        if (!$ok) {
            throw new \RuntimeException("Could not change booking timeslot.");
        }
    }

    public function countPaidBookings(): int
    {
        return $this->bookingRepository->countPaid();
    }
}
