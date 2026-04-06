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

    public function markPaid(int $transactionId, int $bookingId, ?string $providerPaymentIntentId): bool;

    public function markFailed(int $transactionId, string $reason): bool;

    public function markCancelled(int $transactionId, ?string $reason = null): bool;

    public function markTutorCancelledByBooking(int $bookingId, string $reason): bool;
}
