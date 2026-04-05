<?php

namespace App\Models;

final class UserModel
{
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TUTOR = 'tutor';
    public const ROLE_STUDENT = 'student';

    public function __construct(
        public ?int $id,
        public string $name,
        public string $email,
        public string $passwordHash,
        public string $role = self::ROLE_STUDENT,
        public ?string $createdAt = null
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int)$row['id'] : null,
            name: (string)($row['name'] ?? ''),
            email: (string)($row['email'] ?? ''),
            passwordHash: (string)($row['password_hash'] ?? ''),
            role: (string)($row['role'] ?? self::ROLE_STUDENT),
            createdAt: $row['created_at'] ?? null
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isTutor(): bool
    {
        return $this->role === self::ROLE_TUTOR;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public static function isAllowedRole(string $role): bool
    {
        return in_array($role, [self::ROLE_ADMIN, self::ROLE_TUTOR, self::ROLE_STUDENT], true);
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
