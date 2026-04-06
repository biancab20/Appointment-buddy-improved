<?php

namespace App\Services\Interfaces;

use App\Models\BookingModel;

interface IBookingService
{
    /** Student creates a booking (status = paid) */
    public function requestBooking(int $studentId, int $timeslotId): int;

    /** Student views own bookings */
    public function getBookingsForUser(int $userId): array;

    /**
     * Student views own bookings with scope/date filters and pagination.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getBookingsForUserPaginated(
        int $userId,
        string $scope,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage
    ): array;

    /**
     * Tutor views bookings for services they own with filters/pagination.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getBookingsForTutorPaginated(
        int $tutorId,
        string $scope,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage
    ): array;

    /**
     * Tutor calendar date counts for month view.
     *
     * @return array<int, array{date: string, count: int}>
     */
    public function getTutorDateCountsForMonth(int $tutorId, string $scope, int $year, int $month): array;

    /** Admin views all bookings */
    public function getAllBookings(): array;

    /** Student cancels own booking */
    public function cancelBookingForUser(int $bookingId, int $userId): bool;

    /**
     * Student cancels own booking and receives refund policy outcome.
     *
     * @return array{refund_eligible: bool, message: string}
     */
    public function cancelBookingWithPolicyForUser(int $bookingId, int $userId): array;

    /** Find booking by id for a specific user when he wants to change the timeslot */
    public function findByIdForUser(int $bookingId, int $userId): ?array;

    /** Update timeslot for own paid booking */
    public function updateTimeslotForPaid(int $bookingId, int $userId, int $newTimeslotId): bool;

    /** Admin updates status */
    public function updateBookingStatus(int $bookingId, string $status): bool;

    /** Dashboard */
    public function countUpcomingForUser(int $userId): int;

    /** User changes timeslot for paid booking */
    public function changePaidBookingTimeslot(int $bookingId, int $userId, int $newTimeslotId): void;

    /**
     * Student gets reschedule options for a paid booking.
     *
     * @return array{booking: array<string, mixed>, timeslots: array<int, array<string, mixed>>}
     */
    public function getRescheduleOptionsForUser(int $bookingId, int $userId): array;

    /**
     * Tutor cancels a booked appointment.
     * Student is always eligible for refund when tutor initiates cancellation.
     *
     * @return array{refund_eligible: bool, message: string}
     */
    public function cancelBookingForTutor(int $bookingId, int $tutorId): array;

    /** Admin dashboard */
    public function countPaidBookings(): int;
}
