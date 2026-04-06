<?php

namespace App\Repositories;

use App\Config\Database;
use App\Repositories\Interfaces\ITransactionRepository;
use PDO;

class TransactionRepository implements ITransactionRepository
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
    ): int {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                student_id,
                tutor_id,
                service_id,
                timeslot_id,
                provider,
                provider_session_id,
                provider_payment_intent_id,
                amount,
                currency,
                status
            ) VALUES (
                :student_id,
                :tutor_id,
                :service_id,
                :timeslot_id,
                'stripe',
                :provider_session_id,
                :provider_payment_intent_id,
                :amount,
                :currency,
                'pending'
            )
        ");

        $stmt->execute([
            ':student_id' => $studentId,
            ':tutor_id' => $tutorId,
            ':service_id' => $serviceId,
            ':timeslot_id' => $timeslotId,
            ':provider_session_id' => $providerSessionId,
            ':provider_payment_intent_id' => $providerPaymentIntentId,
            ':amount' => $amount,
            ':currency' => $currency,
        ]);

        return (int)$pdo->lastInsertId();
    }

    public function findBySessionId(string $providerSessionId): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT *
            FROM transactions
            WHERE provider_session_id = :provider_session_id
            LIMIT 1
        ");
        $stmt->execute([':provider_session_id' => $providerSessionId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function markPaid(int $transactionId, int $bookingId, ?string $providerPaymentIntentId): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE transactions
            SET status = 'paid',
                booking_id = :booking_id,
                provider_payment_intent_id = :provider_payment_intent_id,
                failure_reason = NULL,
                paid_at = NOW()
            WHERE id = :id
              AND status <> 'paid'
        ");

        $stmt->execute([
            ':id' => $transactionId,
            ':booking_id' => $bookingId,
            ':provider_payment_intent_id' => $providerPaymentIntentId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markFailed(int $transactionId, string $reason): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE transactions
            SET status = 'failed',
                failure_reason = :reason
            WHERE id = :id
              AND status = 'pending'
        ");

        $stmt->execute([
            ':id' => $transactionId,
            ':reason' => substr($reason, 0, 255),
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markCancelled(int $transactionId, ?string $reason = null): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE transactions
            SET status = 'cancelled',
                failure_reason = :reason
            WHERE id = :id
              AND status = 'pending'
        ");

        $stmt->execute([
            ':id' => $transactionId,
            ':reason' => $reason ? substr($reason, 0, 255) : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markTutorCancelledByBooking(int $bookingId, string $reason): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE transactions
            SET status = 'cancelled',
                failure_reason = :reason
            WHERE booking_id = :booking_id
              AND status = 'paid'
        ");
        $stmt->execute([
            ':booking_id' => $bookingId,
            ':reason' => substr($reason, 0, 255),
        ]);

        return $stmt->rowCount() > 0;
    }
}
