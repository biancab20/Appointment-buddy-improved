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
        SELECT b.id, b.status, b.timeslot_id, t.service_id, t.start_time
        FROM bookings b
        JOIN timeslots t ON b.timeslot_id = t.id
        WHERE b.id = :id AND b.student_id = :user_id
        LIMIT 1
    ");
        $stmt->execute([':id' => $bookingId, ':user_id' => $userId]);
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
