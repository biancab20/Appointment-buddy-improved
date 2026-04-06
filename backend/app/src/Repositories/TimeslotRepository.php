<?php

namespace App\Repositories;

use App\Config\Database;
use App\Models\TimeslotModel;
use App\Repositories\Interfaces\ITimeslotRepository;
use PDO;

class TimeslotRepository implements ITimeslotRepository
{
    public function create(TimeslotModel $timeslot): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO timeslots (service_id, start_time, end_time, is_active)
            VALUES (:service_id, :start_time, :end_time, :is_active)
        ");

        $stmt->execute([
            ':service_id' => $timeslot->serviceId,
            ':start_time' => $timeslot->startTime,
            ':end_time' => $timeslot->endTime,
            ':is_active' => $timeslot->isActive ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function find(int $id): ?TimeslotModel
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM timeslots WHERE id = :id");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? TimeslotModel::fromArray($row) : null;
    }

    /** @return TimeslotModel[] */
    public function getUpcomingForService(int $serviceId): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT t.*
            FROM timeslots t
            WHERE t.service_id = :service_id
              AND t.is_active = 1
              AND t.start_time > NOW()
              AND NOT EXISTS (
                SELECT 1
                FROM bookings b
                WHERE b.timeslot_id = t.id
                AND b.status = 'paid'
              )
            ORDER BY t.start_time ASC
        ");

        $stmt->execute([':service_id' => $serviceId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $row) => TimeslotModel::fromArray($row), $rows);
    }

    public function setActive(int $timeslotId, bool $isActive): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE timeslots
            SET is_active = :is_active
            WHERE id = :id
        ");

        $stmt->execute([
            ':is_active' => $isActive ? 1 : 0,
            ':id' => $timeslotId,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function countUpcomingAvailableForService(int $serviceId): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM timeslots t
        WHERE t.service_id = :service_id
          AND t.is_active = 1
          AND t.start_time > NOW()
          AND NOT EXISTS (
            SELECT 1
            FROM bookings b
            WHERE b.timeslot_id = t.id
              AND b.status = 'paid'
          )
    ");

        $stmt->execute([':service_id' => $serviceId]);
        return (int) $stmt->fetchColumn();
    }
    public function getServiceIdForTimeslot(int $timeslotId): ?int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT service_id FROM timeslots WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $timeslotId]);
        $val = $stmt->fetchColumn();
        return $val === false ? null : (int) $val;
    }

    public function getAllForService(int $serviceId): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
        SELECT *
        FROM timeslots
        WHERE service_id = :service_id
        ORDER BY start_time DESC
    ");
        $stmt->execute([':service_id' => $serviceId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row) => TimeslotModel::fromArray($row), $rows);
    }

    public function update(TimeslotModel $timeslot): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
        UPDATE timeslots
        SET start_time = :start_time,
            end_time = :end_time
        WHERE id = :id
    ");

        $stmt->execute([
            ':start_time' => $timeslot->startTime,
            ':end_time' => $timeslot->endTime,
            ':id' => $timeslot->id,
        ]);

        return $stmt->rowCount() > 0;
    }
}
