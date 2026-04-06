<?php

namespace App\Repositories;
use App\Config\Database;
use App\Models\UserModel;
use App\Repositories\Interfaces\IUserRepository;
use PDO;

class UserRepository implements IUserRepository
{
    public function create(UserModel $user): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password_hash, role)
            VALUES (:name, :email, :password_hash, :role)
        ");
        $stmt->execute([
            ':name' => $user->name,
            ':email' => $user->email,
            ':password_hash' => $user->passwordHash,
            ':role' => $user->role,
        ]);

        return (int)$pdo->lastInsertId();
    }
    public function getPaginated(array $filters, int $page, int $perPage): array
    {
        $pdo = Database::getConnection();
        $offset = ($page - 1) * $perPage;

        $whereParts = ['1 = 1'];
        $params = [];

        $role = strtolower(trim((string)($filters['role'] ?? '')));
        if ($role !== '') {
            $whereParts[] = 'u.role = :role';
            $params[':role'] = $role;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $whereParts[] = '(u.name LIKE :search OR u.email LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = implode(' AND ', $whereParts);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$whereSql}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, (string)$value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("\n            SELECT u.*\n            FROM users u\n            WHERE {$whereSql}\n            ORDER BY u.created_at DESC, u.id DESC\n            LIMIT :limit OFFSET :offset\n        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, (string)$value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => array_map(fn(array $row) => UserModel::fromArray($row), $rows),
            'total' => $total,
        ];
    }

    public function findByEmail(string $email): ?UserModel
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? UserModel::fromArray($row) : null;
    }

    public function findById(int $id): ?UserModel
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? UserModel::fromArray($row) : null;
    }
}

