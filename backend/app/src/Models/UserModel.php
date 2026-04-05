<?php

namespace App\Models;

final class UserModel
{
    public function __construct(
        public ?int $id,
        public string $name,
        public string $email,
        public string $passwordHash,
        public string $role = 'student',
        public ?string $createdAt = null
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int)$row['id'] : null,
            name: (string)($row['name'] ?? ''),
            email: (string)($row['email'] ?? ''),
            passwordHash: (string)($row['password_hash'] ?? ''),
            role: (string)($row['role'] ?? 'student'),
            createdAt: $row['created_at'] ?? null
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'password_hash' => $this->passwordHash,
            'role' => $this->role,
            'created_at' => $this->createdAt,
        ];
    }
}
