<?php

namespace App\Repositories\Interfaces;

use App\Models\BookingModel;

interface IBookingRepository
{
    /** Create new booking */
    public function create(BookingModel $booking): int;

    /** Get all bookings for a specific student */
    public function forUser(int $userId): array;

    /**
     * Get student bookings with scope/date filters and pagination.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getForStudentPaginated(
        int $userId,
        string $scope,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage
    ): array;

    /**
     * Get tutor-owned bookings with scope/date filters and pagination.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getForTutorPaginated(
        int $tutorId,
        string $scope,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage
    ): array;

    /**
     * Get booking counts per date for a tutor month view.
     *
     * @return array<int, array{date: string, count: int}>
     */
    public function getTutorDateCountsForMonth(int $tutorId, string $scope, int $year, int $month): array;

    /** Get all bookings for admin */
    public function getAll(): array;

    /** Admin action: update booking status */
    public function updateStatus(int $bookingId, string $status): bool;

    /** Student action: Cancel own booking */
    public function cancelForUser(int $bookingId, int $userId): bool;

    /** Helper: Find booking by id for a specific student*/
    public function findByIdForUser(int $bookingId, int $userId): ?array;

    /** Helper: Find booking by id for a specific tutor (service owner). */
    public function findByIdForTutor(int $bookingId, int $tutorId): ?array;

    /** Student action: Update own paid booking timeslot */
    public function updateTimeslotForPaid(int $bookingId, int $userId, int $newTimeslotId): bool;

    /** Tutor action: Cancel own paid booking. */
    public function cancelForTutor(int $bookingId, int $tutorId): bool;

    /** Helper: Prevent duplicates by same student */
    public function existsForUserAndTimeslot(int $userId, int $timeslotId): bool;

    /** Helper: Prevent double-booking of the same timeslot by different users */
    public function existsActiveForTimeslot(int $timeslotId): bool;

    /** Student dashboard: Count upcoming bookings */
    public function countUpcomingForUser(int $userId): int;

    /** Helper: Count active timeslots */
    public function countActiveForTimeslot(int $timeslotId): int;

    /** Helper: Mark paid bookings as cancelled for a specific timeslot */
    public function cancelPaidForTimeslot(int $timeslotId): int;

    /** Admin dashboard: Count paid bookings */
    public function countPaid(): int;
}
