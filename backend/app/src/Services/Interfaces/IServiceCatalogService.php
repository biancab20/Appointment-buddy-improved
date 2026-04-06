<?php

namespace App\Services\Interfaces;

use App\Models\ServiceModel;

interface IServiceCatalogService
{
    /** @return ServiceModel[] */
    public function getAllServices(): array;

    /** @return ServiceModel[] */
    public function getServicesByTutorId(int $tutorId): array;

    public function getAllActiveServices(): array;

    public function getService(int $id): ?ServiceModel;

    public function createService(
        int $tutorId,
        string $title,
        ?string $description,
        int $durationMinutes,
        float $price
    ): int;

    public function updateService(int $id, string $title, ?string $description, int $durationMinutes, float $price): void;

    public function deactivateService(int $id): void;

    public function activateService(int $id): void;
}
