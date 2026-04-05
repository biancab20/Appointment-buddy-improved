<?php

namespace App\Models;

final class BookingModel
{
    public function __construct(
        public ?int $id,
        public int $studentId,
        public int $timeslotId,
        public string $status = 'pending',
        public ?string $createdAt = null
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int)$row['id'] : null,
            studentId: (int)($row['student_id'] ?? 0),
            timeslotId: (int)($row['timeslot_id'] ?? 0),
            status: (string)($row['status'] ?? 'pending'),
            createdAt: $row['created_at'] ?? null
        );
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->studentId,
            'timeslot_id' => $this->timeslotId,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
