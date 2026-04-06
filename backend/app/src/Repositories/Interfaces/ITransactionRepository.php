<?php

namespace App\Repositories\Interfaces;

interface ITransactionRepository
{
    public function createPending(
        int $studentId,
        int $tutorId,
        int $serviceId,
        int $timeslotId,
        string $providerSessionId,
        ?string $providerPaymentIntentId,
        float $amount,
        string $currency
    ): int;

    public function findBySessionId(string $providerSessionId): ?array;

    /**
     * @param array{
     *   status?: string,
     *   provider?: string,
     *   currency?: string,
     *   student_id?: int|null,
     *   tutor_id?: int|null,
     *   service_id?: int|null,
     *   timeslot_id?: int|null,
     *   booking_id?: int|null,
     *   date_from?: string|null,
     *   date_to?: string|null
     * } $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getPaginated(array $filters, int $page, int $perPage): array;

    public function markPaid(int $transactionId, int $bookingId, ?string $providerPaymentIntentId): bool;

    public function markFailed(int $transactionId, string $reason): bool;

    public function markCancelled(int $transactionId, ?string $reason = null): bool;

    public function markTutorCancelledByBooking(int $bookingId, string $reason): bool;
}
