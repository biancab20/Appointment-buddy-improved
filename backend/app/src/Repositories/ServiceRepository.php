<?php

namespace App\Repositories;

use App\Config\Database;
use App\Models\ServiceModel;
use App\Repositories\Interfaces\IServiceRepository;
use PDO;

class ServiceRepository implements IServiceRepository
{
    public function create(ServiceModel $service): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO services (tutor_id, title, description, duration_minutes, price, is_active)
            VALUES (:tutor_id, :title, :description, :duration_minutes, :price, :is_active)
        ");

        $stmt->execute([
            ':tutor_id' => $service->tutorId,
            ':title' => $service->title,
            ':description' => $service->description,
            ':duration_minutes' => $service->durationMinutes,
            ':price' => $service->price,
            ':is_active' => $service->isActive ? 1 : 0,
        ]);

        return (int) $pdo->lastInsertId();
    }

    // Admin //
    /** @return ServiceModel[] */
    public function getAll(): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->query("
            SELECT s.*, u.name AS tutor_name
            FROM services s
            JOIN users u ON u.id = s.tutor_id
            ORDER BY s.title ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn(array $row) => ServiceModel::fromArray($row), $rows);
    }

    // Student //
    public function getAllActive(): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->query("
            SELECT s.*, u.name AS tutor_name
            FROM services s
            JOIN users u ON u.id = s.tutor_id
            WHERE s.is_active = 1
            ORDER BY s.title ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => ServiceModel::fromArray($row), $rows);
    }

    public function getAllByTutorId(int $tutorId): array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT s.*, u.name AS tutor_name
            FROM services s
            JOIN users u ON u.id = s.tutor_id
            WHERE s.tutor_id = :tutor_id
            ORDER BY s.title ASC
        ");
        $stmt->execute([':tutor_id' => $tutorId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row) => ServiceModel::fromArray($row), $rows);
    }

    public function find(int $id): ?ServiceModel
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT s.*, u.name AS tutor_name
            FROM services s
            JOIN users u ON u.id = s.tutor_id
            WHERE s.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ServiceModel::fromArray($row) : null;
    }

    public function update(ServiceModel $service): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            UPDATE services
            SET title = :title,
                description = :description,
                duration_minutes = :duration_minutes,
                price = :price
            WHERE id = :id
        ");

        $stmt->execute([
            ':title' => $service->title,
            ':description' => $service->description,
            ':duration_minutes' => $service->durationMinutes,
            ':price' => $service->price,
            ':id' => $service->id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deactivate(int $serviceId): bool
    {
        $pdo = Database::getConnection();

        // 1) deactivate service
        $stmt = $pdo->prepare("
            UPDATE services
            SET is_active = 0
            WHERE id = :id
        ");
        $stmt->execute([':id' => $serviceId]);

        // 2) deactivate all timeslots for that service
        $stmt2 = $pdo->prepare("
            UPDATE timeslots
            SET is_active = 0
            WHERE service_id = :id
        ");
        $stmt2->execute([':id' => $serviceId]);

        return true;
    }

    public function activate(int $serviceId): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
        UPDATE services
        SET is_active = 1
        WHERE id = :id
    ");
        $stmt->execute([':id' => $serviceId]);

        return $stmt->rowCount() > 0;
    }

    public function isActive(int $serviceId): bool
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT is_active FROM services WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $serviceId]);

        $val = $stmt->fetchColumn();
        return (int) $val === 1;
    }
}
