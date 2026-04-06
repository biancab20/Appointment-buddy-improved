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
