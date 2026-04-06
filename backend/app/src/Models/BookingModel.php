<?php

namespace App\Models;

final class BookingModel
{
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        public ?int $id,
        public int $studentId,
        public int $timeslotId,
        public float $priceAtBooking = 0.0,
        public string $status = self::STATUS_PAID,
        public ?string $createdAt = null
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int)$row['id'] : null,
            studentId: (int)($row['student_id'] ?? 0),
            timeslotId: (int)($row['timeslot_id'] ?? 0),
            priceAtBooking: (float)($row['price_at_booking'] ?? 0),
            status: (string)($row['status'] ?? self::STATUS_PAID),
            createdAt: $row['created_at'] ?? null
        );
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->studentId,
            'timeslot_id' => $this->timeslotId,
            'price_at_booking' => $this->priceAtBooking,
            'status' => $this->status,
            'created_at' => $this->createdAt,
        ];
    }
}
