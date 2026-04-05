<?php

namespace App\Services;

use App\Models\ServiceModel;
use App\Repositories\ServiceRepository;
use App\Repositories\Interfaces\IServiceRepository;
use App\Services\Interfaces\IServiceCatalogService;

class ServiceCatalogService implements IServiceCatalogService
{
    private IServiceRepository $serviceRepository;

    public function __construct()
    {
        $this->serviceRepository = new ServiceRepository();
    }

    public function getAllServices(): array
    {
        return $this->serviceRepository->getAll();
    }

    public function getAllActiveServices(): array
    {
        return $this->serviceRepository->getAllActive();
    }

    public function getService(int $id): ?ServiceModel
    {
        return $this->serviceRepository->find($id);
    }

    public function createService(string $title, ?string $description, int $durationMinutes): int
    {
        $title = trim($title);
        if ($title === '') {
            throw new \RuntimeException("Service title is required.");
        }

        if ($durationMinutes <= 0) {
            throw new \RuntimeException("Duration must be greater than 0.");
        }

        $service = new ServiceModel(
            id: null,
            title: $title,
            description: $description ? trim($description) : null,
            durationMinutes: $durationMinutes,
            isActive: true,
            createdAt: null
        );

        return $this->serviceRepository->create($service);
    }

    public function updateService(int $id, string $title, ?string $description, int $durationMinutes): void
    {
        $service = $this->serviceRepository->find($id);
        if (!$service) {
            throw new \RuntimeException("Service not found.");
        }

        $service->title = trim($title);
        $service->description = $description ? trim($description) : null;
        $service->durationMinutes = $durationMinutes;

        $this->serviceRepository->update($service);
    }

    public function deactivateService(int $id): void
    {
        $service = $this->serviceRepository->find($id);
        if (!$service) {
            throw new \RuntimeException("Service not found.");
        }

        $this->serviceRepository->deactivate($id);
    }

    public function activateService(int $id): void
    {
        $service = $this->serviceRepository->find($id);
        if (!$service) {
            throw new \RuntimeException("Service not found.");
        }

        $this->serviceRepository->activate($id);
    }
}
