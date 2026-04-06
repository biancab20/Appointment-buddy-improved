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

    
    public function getPaginated(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $perPage;

        $whereParts = ['1 = 1'];
        $params = [];

        $status = strtolower(trim((string)($filters['status'] ?? '')));
        if ($status !== '') {
            $whereParts[] = 'tr.status = :status';
            $params[':status'] = $status;
        }

        $provider = strtolower(trim((string)($filters['provider'] ?? '')));
        if ($provider !== '') {
            $whereParts[] = 'tr.provider = :provider';
            $params[':provider'] = $provider;
        }

        $currency = strtolower(trim((string)($filters['currency'] ?? '')));
        if ($currency !== '') {
            $whereParts[] = 'tr.currency = :currency';
            $params[':currency'] = $currency;
        }

        foreach (['student_id', 'tutor_id', 'service_id', 'timeslot_id', 'booking_id'] as $intFilter) {
            if (array_key_exists($intFilter, $filters) && $filters[$intFilter] !== null) {
                $whereParts[] = "tr.{$intFilter} = :{$intFilter}";
                $params[':' . $intFilter] = (int)$filters[$intFilter];
            }
        }

        if (array_key_exists('date_from', $filters) && $filters['date_from'] !== null) {
            $whereParts[] = 'DATE(tr.created_at) >= :date_from';
            $params[':date_from'] = (string)$filters['date_from'];
        }

        if (array_key_exists('date_to', $filters) && $filters['date_to'] !== null) {
            $whereParts[] = 'DATE(tr.created_at) <= :date_to';
            $params[':date_to'] = (string)$filters['date_to'];
        }

        $whereSql = implode(' AND ', $whereParts);

        $countStmt = $pdo->prepare("\n            SELECT COUNT(*)\n            FROM transactions tr\n            WHERE {$whereSql}\n        ");
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $countStmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $countStmt->bindValue($key, (string)$value, PDO::PARAM_STR);
            }
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("\n            SELECT\n                tr.*,\n                student.name AS student_name,\n                student.email AS student_email,\n                tutor.name AS tutor_name,\n                tutor.email AS tutor_email,\n                s.title AS service_title,\n                t.start_time,\n                t.end_time\n            FROM transactions tr\n            JOIN users student ON tr.student_id = student.id\n            JOIN users tutor ON tr.tutor_id = tutor.id\n            JOIN services s ON tr.service_id = s.id\n            JOIN timeslots t ON tr.timeslot_id = t.id\n            WHERE {$whereSql}\n            ORDER BY tr.created_at DESC, tr.id DESC\n            LIMIT :limit OFFSET :offset\n        ");
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, (string)$value, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
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

