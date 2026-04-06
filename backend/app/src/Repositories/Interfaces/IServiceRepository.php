<?php

namespace App\Repositories\Interfaces;

use App\Models\ServiceModel;

interface IServiceRepository
{
    /** Admin action: Create new service*/
    public function create(ServiceModel $service): int;

    /** Admin action: Get all services*/
    /** @return ServiceModel[] */
    public function getAll(): array;

    /** Student action: Get all active services*/
    /** @return ServiceModel[] */
    public function getAllActive(): array;

    /** Tutor action: Get all owned services */
    /** @return ServiceModel[] */
    public function getAllByTutorId(int $tutorId): array;

    /** Helper: Get service by id*/
    public function find(int $id): ?ServiceModel;

    /** Helper: Update service*/
    public function update(ServiceModel $service): bool;

    /** Helper: Deactivate a service (delete)*/
    public function deactivate(int $serviceId): bool;

    /** Helper: Activate a service */
    public function activate(int $serviceId): bool;

    /** Helper: Check if the service is active */
    public function isActive(int $serviceId): bool;
}
