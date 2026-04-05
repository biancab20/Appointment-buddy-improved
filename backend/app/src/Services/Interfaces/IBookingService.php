<?php

namespace App\Services\Interfaces;

use App\Models\BookingModel;

interface IBookingService
{
    /** Student requests a booking (usually status = pending) */
    public function requestBooking(int $studentId, int $timeslotId): int;

    /** Student views own bookings */
    public function getBookingsForUser(int $userId): array;

    /** Admin views all bookings */
    public function getAllBookings(): array;

    /** Student cancels own booking */
    public function cancelBookingForUser(int $bookingId, int $userId): bool;

    /** Find booking by id for a specific user when he wants to change the timeslot */
    public function findByIdForUser(int $bookingId, int $userId): ?array;

    /** Update timeslot when user changes/updated a booking */
    public function updateTimeslotForPending(int $bookingId, int $userId, int $newTimeslotId): bool;
    
    /** Admin approves/cancels */
    public function updateBookingStatus(int $bookingId, string $status): bool;

    /** Dashboard */
    public function countUpcomingForUser(int $userId): int;

    /** User changes timeslot for booking */
    public function changePendingBookingTimeslot(int $bookingId, int $userId, int $newTimeslotId): void;

    /** Admin dashboard */
    public function countPendingBookings(): int;
}
