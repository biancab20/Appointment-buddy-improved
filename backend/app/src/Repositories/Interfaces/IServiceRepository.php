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

    /**
     * Admin action: Get all services with pagination and filters.
     * @param array{
     *   subject?: string,
     *   tutor_id?: int|null,
     *   is_active?: bool|null,
     *   min_duration?: int|null,
     *   max_duration?: int|null,
     *   min_price?: float|null,
     *   max_price?: float|null
     * } $filters
     * @return array{items: ServiceModel[], total: int}
     */
    public function getAllPaginated(array $filters, int $page, int $perPage): array;

    /** Student action: Get all active services*/
    /** @return ServiceModel[] */
    public function getAllActive(): array;

    /**
     * Student action: Get active services with pagination and filters.
     * @param array{
     *   subject?: string,
     *   min_duration?: int|null,
     *   max_duration?: int|null,
     *   min_price?: float|null,
     *   max_price?: float|null
     * } $filters
     * @return array{items: ServiceModel[], total: int}
     */
    public function getActivePaginated(array $filters, int $page, int $perPage): array;

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
