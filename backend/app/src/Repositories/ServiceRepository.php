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

    public function getActivePaginated(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildActiveFilters($filters);

        $countStmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM services s
            JOIN users u ON u.id = s.tutor_id
            {$whereSql}
        ");
        foreach ($params as $name => $value) {
            if (is_int($value)) {
                $countStmt->bindValue($name, $value, PDO::PARAM_INT);
            } else {
                $countStmt->bindValue($name, (string)$value, PDO::PARAM_STR);
            }
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT s.*, u.name AS tutor_name
            FROM services s
            JOIN users u ON u.id = s.tutor_id
            {$whereSql}
            ORDER BY s.title ASC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $name => $value) {
            if (is_int($value)) {
                $stmt->bindValue($name, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($name, (string)$value, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => array_map(fn(array $row) => ServiceModel::fromArray($row), $rows),
            'total' => $total,
        ];
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

        // 2) deactivate only timeslots that do not have paid bookings.
        // Booked timeslots stay active so the tutor can still deliver those sessions.
        $stmt2 = $pdo->prepare("
            UPDATE timeslots t
            LEFT JOIN bookings b
              ON b.timeslot_id = t.id
             AND b.status = 'paid'
            SET t.is_active = 0
            WHERE t.service_id = :id
              AND b.id IS NULL
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

    /**
     * @return array{0: string, 1: array<string, int|string>}
     */
    private function buildActiveFilters(array $filters): array
    {
        $conditions = ['s.is_active = 1'];
        $params = [];

        $subject = trim((string)($filters['subject'] ?? ''));
        if ($subject !== '') {
            $conditions[] = 's.title LIKE :subject';
            $params[':subject'] = '%' . $subject . '%';
        }

        if (array_key_exists('min_duration', $filters) && $filters['min_duration'] !== null) {
            $conditions[] = 's.duration_minutes >= :min_duration';
            $params[':min_duration'] = (int) $filters['min_duration'];
        }

        if (array_key_exists('max_duration', $filters) && $filters['max_duration'] !== null) {
            $conditions[] = 's.duration_minutes <= :max_duration';
            $params[':max_duration'] = (int) $filters['max_duration'];
        }

        if (array_key_exists('min_price', $filters) && $filters['min_price'] !== null) {
            $conditions[] = 's.price >= :min_price';
            $params[':min_price'] = number_format((float) $filters['min_price'], 2, '.', '');
        }

        if (array_key_exists('max_price', $filters) && $filters['max_price'] !== null) {
            $conditions[] = 's.price <= :max_price';
            $params[':max_price'] = number_format((float) $filters['max_price'], 2, '.', '');
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }
}
