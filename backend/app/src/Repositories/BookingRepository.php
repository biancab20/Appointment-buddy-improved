<?php

namespace App\Repositories;
use App\Config\Database;
use App\Models\BookingModel;
use App\Repositories\Interfaces\IBookingRepository;
use PDO;

class BookingRepository implements IBookingRepository
{
    public function getAll(): array
    {
        $pdo = Database::getConnection();

        // Useful for admin view: who booked what, when, and status
        $stmt = $pdo->query("
            SELECT
                b.id,
                b.status,
                b.created_at,
                b.price_at_booking,
                u.name AS student_name,
                u.email AS student_email,
                s.title AS service_title,
                t.start_time,
                t.end_time
            FROM bookings b
            JOIN users u ON b.student_id = u.id
            JOIN timeslots t ON b.timeslot_id = t.id
            JOIN services s ON t.service_id = s.id
            ORDER BY t.start_time DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllPaginated(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $perPage;

        $whereParts = ['1 = 1'];
        $params = [];

        $status = strtolower(trim((string)($filters['status'] ?? '')));
        if ($status !== '') {
            $whereParts[] = 'b.status = :status';
            $params[':status'] = $status;
        }

        $scope = strtolower(trim((string)($filters['scope'] ?? '')));
        if ($scope === 'upcoming') {
            $whereParts[] = "b.status = 'paid'";
            $whereParts[] = 't.start_time >= NOW()';
        } elseif ($scope === 'history') {
            $whereParts[] = "(t.start_time < NOW() OR b.status = 'cancelled')";
        }

        if (array_key_exists('student_id', $filters) && $filters['student_id'] !== null) {
            $whereParts[] = 'b.student_id = :student_id';
            $params[':student_id'] = (int)$filters['student_id'];
        }

        if (array_key_exists('tutor_id', $filters) && $filters['tutor_id'] !== null) {
            $whereParts[] = 's.tutor_id = :tutor_id';
            $params[':tutor_id'] = (int)$filters['tutor_id'];
        }

        if (array_key_exists('service_id', $filters) && $filters['service_id'] !== null) {
            $whereParts[] = 's.id = :service_id';
            $params[':service_id'] = (int)$filters['service_id'];
        }

        if (array_key_exists('date_from', $filters) && $filters['date_from'] !== null) {
            $whereParts[] = 'DATE(t.start_time) >= :date_from';
            $params[':date_from'] = (string)$filters['date_from'];
        }

        if (array_key_exists('date_to', $filters) && $filters['date_to'] !== null) {
            $whereParts[] = 'DATE(t.start_time) <= :date_to';
            $params[':date_to'] = (string)$filters['date_to'];
        }

        $whereSql = implode(' AND ', $whereParts);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM bookings b
            JOIN timeslots t ON b.timeslot_id = t.id
            JOIN services s ON t.service_id = s.id
            WHERE {$whereSql}
        ");
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $countStmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $countStmt->bindValue($key, (string)$value, PDO::PARAM_STR);
            }
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.student_id,
                b.timeslot_id,
                b.status,
                b.created_at,
                b.price_at_booking,
                t.start_time,
                t.end_time,
                s.id AS service_id,
                s.title AS service_title,
                s.tutor_id,
                tutor.name AS tutor_name,
                tutor.email AS tutor_email,
                student.name AS student_name,
                student.email AS student_email
            FROM bookings b
            JOIN timeslots t ON b.timeslot_id = t.id
            JOIN services s ON t.service_id = s.id
            JOIN users tutor ON s.tutor_id = tutor.id
            JOIN users student ON b.student_id = student.id
            WHERE {$whereSql}
            ORDER BY t.start_time DESC, b.id DESC
            LIMIT :limit OFFSET :offset
        ");
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

    public function create(BookingModel $booking): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO bookings (student_id, timeslot_id, price_at_booking, status)
            VALUES (:student_id, :timeslot_id, :price_at_booking, :status)
        ");

        $stmt->execute([
            ':student_id' => $booking->studentId,
            ':timeslot_id' => $booking->timeslotId,
            ':price_at_booking' => $booking->priceAtBooking,
            ':status' => $booking->status,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function countUpcomingForUser(int $userId): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM bookings b
            JOIN timeslots t ON b.timeslot_id = t.id
            WHERE b.student_id = :id
              AND b.status = 'paid'
              AND t.start_time > NOW()
        ");

        $stmt->execute([':id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function forUser(int $userId): array
    {
        $pdo = Database::getConnection();

        // Get bookings with service + timeslot info
        $stmt = $pdo->prepare("
            SELECT 
                b.id,
                b.status,
                b.created_at,
                b.price_at_booking,
                t.start_time,
                t.end_time,
                s.id AS service_id,
                s.title AS service_title
            FROM bookings b
            JOIN timeslots t ON b.timeslot_id = t.id
            JOIN services s ON t.service_id = s.id
            WHERE b.student_id = :id
            ORDER BY t.start_time ASC
        ");

        $stmt->execute([':id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getForStudentPaginated(
        int $userId,
        string $scope,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage
    ): array {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $perPage;

        $whereParts = ['b.student_id = :user_id'];
        $params = [':user_id' => $userId];

        if ($scope === 'upcoming') {
            $whereParts[] = "b.status = 'paid'";
            $whereParts[] = "t.start_time >= NOW()";
        } else {
            $whereParts[] = "(t.start_time < NOW() OR b.status = 'cancelled')";
        }

        if ($dateFrom !== null) {
            $whereParts[] = 'DATE(t.start_time) >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $whereParts[] = 'DATE(t.start_time) <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        $whereSql = implode(' AND ', $whereParts);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM bookings b
            JOIN timeslots t ON b.timeslot_id = t.id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $orderDirection = $scope === 'upcoming' ? 'ASC' : 'DESC';

        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.status,
                b.created_at,
                b.price_at_booking,
                b.timeslot_id,
                t.start_time,
                t.end_time,
                s.id AS service_id,
                s.title AS service_title,
                u.id AS tutor_id,
                u.name AS tutor_name
            FROM bookings b
            JOIN timeslots t ON b.timeslot_id = t.id
            JOIN services s ON t.service_id = s.id
            JOIN users u ON s.tutor_id = u.id
            WHERE {$whereSql}
            ORDER BY t.start_time {$orderDirection}
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    public function getForTutorPaginated(
        int $tutorId,
        string $scope,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage
    ): array {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $perPage;

        $whereParts = ['s.tutor_id = :tutor_id'];
        $params = [':tutor_id' => $tutorId];

        if ($scope === 'upcoming') {
            $whereParts[] = "b.status = 'paid'";
            $whereParts[] = "t.start_time >= NOW()";
        } else {
            $whereParts[] = "(t.start_time < NOW() OR b.status = 'cancelled')";
        }

        if ($dateFrom !== null) {
            $whereParts[] = 'DATE(t.start_time) >= :date_from';
            $params[':date_from'] = $dateFrom;
        }

        if ($dateTo !== null) {
            $whereParts[] = 'DATE(t.start_time) <= :date_to';
            $params[':date_to'] = $dateTo;
        }

        $whereSql = implode(' AND ', $whereParts);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM bookings b
            JOIN timeslots t ON b.timeslot_id = t.id
            JOIN services s ON t.service_id = s.id
            WHERE {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $orderDirection = $scope === 'upcoming' ? 'ASC' : 'DESC';

        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.status,
                b.created_at,
                b.price_at_booking,
                b.timeslot_id,
                t.start_time,
                t.end_time,
                s.id AS service_id,
                s.title AS service_title,
                u.id AS student_id,
                u.name AS student_name,
                u.email AS student_email
            FROM bookings b
            JOIN timeslots t ON b.timeslot_id = t.id
            JOIN services s ON t.service_id = s.id
            JOIN users u ON b.student_id = u.id
            WHERE {$whereSql}
            ORDER BY t.start_time {$orderDirection}
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    public function getTutorDateCountsForMonth(int $tutorId, string $scope, int $year, int $month): array
    {
        $pdo = Database::getConnection();

        $whereParts = [
            's.tutor_id = :tutor_id',
            'YEAR(t.start_time) = :year',
            'MONTH(t.start_time) = :month',
        ];
        $params = [
            ':tutor_id' => $tutorId,
            ':year' => $year,
            ':month' => $month,
        ];

        if ($scope === 'upcoming') {
            $whereParts[] = "b.status = 'paid'";
            $whereParts[] = "t.start_time >= NOW()";
        } else {
            $whereParts[] = "(t.start_time < NOW() OR b.status = 'cancelled')";
        }

        $whereSql = implode(' AND ', $whereParts);

        $stmt = $pdo->prepare("
            SELECT
                DATE(t.start_time) AS booking_date,
                COUNT(*) AS booking_count
            FROM bookings b
            JOIN timeslots t ON b.timeslot_id = t.id
            JOIN services s ON t.service_id = s.id
            WHERE {$whereSql}
            GROUP BY DATE(t.start_time)
            ORDER BY DATE(t.start_time) ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            fn(array $row) => [
                'date' => (string)($row['booking_date'] ?? ''),
                'count' => (int)($row['booking_count'] ?? 0),
            ],
            $rows
        );
    }

    public function cancelForUser(int $bookingId, int $userId): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'cancelled'
            WHERE id = :id
              AND student_id = :user_id
              AND status <> 'cancelled'
        ");

        $stmt->execute([
            ':id' => $bookingId,
            ':user_id' => $userId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function existsForUserAndTimeslot(int $userId, int $timeslotId): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM bookings
            WHERE student_id = :user_id
              AND timeslot_id = :timeslot_id
              AND status = 'paid'
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':timeslot_id' => $timeslotId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
    public function existsActiveForTimeslot(int $timeslotId): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM bookings
        WHERE timeslot_id = :timeslot_id
          AND status = 'paid'
    ");

        $stmt->execute([':timeslot_id' => $timeslotId]);

        return (int) $stmt->fetchColumn() > 0;
    }
    public function updateStatus(int $bookingId, string $status): bool
    {
        // keep allowed statuses tight
        $allowed = ['paid', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }

        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
        UPDATE bookings
        SET status = :status
        WHERE id = :id
    ");

        $stmt->execute([
            ':status' => $status,
            ':id' => $bookingId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function findByIdForUser(int $bookingId, int $userId): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
        SELECT b.id, b.status, b.timeslot_id, b.price_at_booking, t.service_id, t.start_time
        FROM bookings b
        JOIN timeslots t ON b.timeslot_id = t.id
        WHERE b.id = :id AND b.student_id = :user_id
        LIMIT 1
    ");
        $stmt->execute([':id' => $bookingId, ':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByIdForTutor(int $bookingId, int $tutorId): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT
                b.id,
                b.status,
                b.timeslot_id,
                b.student_id,
                b.price_at_booking,
                t.service_id,
                t.start_time,
                t.end_time,
                s.title AS service_title
            FROM bookings b
            JOIN timeslots t ON b.timeslot_id = t.id
            JOIN services s ON t.service_id = s.id
            WHERE b.id = :id
              AND s.tutor_id = :tutor_id
            LIMIT 1
        ");
        $stmt->execute([
            ':id' => $bookingId,
            ':tutor_id' => $tutorId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateTimeslotForPaid(int $bookingId, int $userId, int $newTimeslotId): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
        UPDATE bookings
        SET timeslot_id = :new_timeslot_id
        WHERE id = :id
          AND student_id = :user_id
          AND status = 'paid'
    ");
        $stmt->execute([
            ':new_timeslot_id' => $newTimeslotId,
            ':id' => $bookingId,
            ':user_id' => $userId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function cancelForTutor(int $bookingId, int $tutorId): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE bookings b
            JOIN timeslots t ON b.timeslot_id = t.id
            JOIN services s ON t.service_id = s.id
            SET b.status = 'cancelled'
            WHERE b.id = :id
              AND s.tutor_id = :tutor_id
              AND b.status = 'paid'
        ");
        $stmt->execute([
            ':id' => $bookingId,
            ':tutor_id' => $tutorId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function countActiveForTimeslot(int $timeslotId): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM bookings
        WHERE timeslot_id = :timeslot_id
          AND status = 'paid'
    ");
        $stmt->execute([':timeslot_id' => $timeslotId]);

        return (int) $stmt->fetchColumn();
    }

    public function cancelPaidForTimeslot(int $timeslotId): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
        UPDATE bookings
        SET status = 'cancelled'
        WHERE timeslot_id = :timeslot_id
          AND status = 'paid'
    ");
        $stmt->execute([':timeslot_id' => $timeslotId]);

        return $stmt->rowCount();
    }

    public function countPaid(): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM bookings
        WHERE status = 'paid'
    ");

        return (int) $stmt->fetchColumn();
    }
}


