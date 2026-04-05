<?php

namespace App\Models;

final class ServiceModel
{
    public function __construct(
        public ?int $id,
        public string $title,
        public ?string $description,
        public int $durationMinutes,
        public bool $isActive = true,
        public ?string $createdAt = null
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int)$row['id'] : null,
            title: (string)($row['title'] ?? ''),
            description: $row['description'] ?? null,
            durationMinutes: (int)($row['duration_minutes'] ?? 0),
            isActive: isset($row['is_active']) ? (bool) $row['is_active'] : true,
            createdAt: $row['created_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'duration_minutes' => $this->durationMinutes,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
        ];
    }
}
