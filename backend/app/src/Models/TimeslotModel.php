<?php

namespace App\Models;

final class TimeslotModel
{
    public function __construct(
        public ?int $id,
        public int $serviceId,
        public string $startTime, 
        public string $endTime,
        public bool $isActive = true,
        public ?string $createdAt = null
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int)$row['id'] : null,
            serviceId: (int)($row['service_id'] ?? 0),
            startTime: (string)($row['start_time'] ?? ''),
            endTime: (string)($row['end_time'] ?? ''),
            isActive: (bool)($row['is_active'] ?? 1),
            createdAt: $row['created_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'service_id' => $this->serviceId,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
        ];
    }
}
