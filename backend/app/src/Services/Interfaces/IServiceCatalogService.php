<?php

namespace App\Services\Interfaces;

use App\Models\ServiceModel;
use App\Models\TimeslotModel;

interface IServiceCatalogService
{
    /** @return ServiceModel[] */
    public function getAllServices(): array;

    public function getAllActiveServices(): array;

    public function getService(int $id): ?ServiceModel;

    public function createService(string $title, ?string $description, int $durationMinutes, float $price): int;

    public function updateService(int $id, string $title, ?string $description, int $durationMinutes, float $price): void;

    public function deactivateService(int $id): void;

    public function activateService(int $id): void;
}