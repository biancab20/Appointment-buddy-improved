<?php

namespace App\Repositories\Interfaces;

use App\Models\BookingModel;

interface IBookingRepository
{
    /** Create new booking */ 
    public function create(BookingModel $booking): int;

    /** Get all bookings for a specific student */
    public function forUser(int $userId): array;

    /** Get all bookings for admin */
    public function getAll(): array;

    /** Admin action: Approve/cancel */
    public function updateStatus(int $bookingId, string $status): bool;

    /** Student action: Cancel own booking */
    public function cancelForUser(int $bookingId, int $userId): bool;

    /** Helper: Find booking by id for a specific student*/
    public function findByIdForUser(int $bookingId, int $userId): ?array;

    /** Student action: Update his own booking timeslot*/
    public function updateTimeslotForPending(int $bookingId, int $userId, int $newTimeslotId): bool;

    /** Helper: Prevent duplicates by same student */
    public function existsForUserAndTimeslot(int $userId, int $timeslotId): bool;

    /** Helper: Prevent double-booking of the same timeslot by different users */
    public function existsActiveForTimeslot(int $timeslotId): bool;

    /** Student dashboard: Count upcoming bookings */
    public function countUpcomingForUser(int $userId): int;

    /** Helper: Count active timeslots */
    public function countActiveForTimeslot(int $timeslotId): int;

    /** Helper: Mark booking as declined for a specific timeslot*/
    public function declineActiveForTimeslot(int $timeslotId): int;

    /** Admin dashboard: Count pending bookings */
    public function countPending(): int;
}
