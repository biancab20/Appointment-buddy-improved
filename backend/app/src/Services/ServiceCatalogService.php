<?php

namespace App\Services;

use App\Models\ServiceModel;
use App\Repositories\ServiceRepository;
use App\Repositories\Interfaces\IServiceRepository;
use App\Repositories\Interfaces\IUserRepository;
use App\Repositories\UserRepository;
use App\Services\Interfaces\IServiceCatalogService;

class ServiceCatalogService implements IServiceCatalogService
{
    private IServiceRepository $serviceRepository;
    private IUserRepository $userRepository;

    public function __construct()
    {
        $this->serviceRepository = new ServiceRepository();
        $this->userRepository = new UserRepository();
    }

    public function getAllServices(): array
    {
        return $this->serviceRepository->getAll();
    }

    public function getServicesByTutorId(int $tutorId): array
    {
        if ($tutorId <= 0) {
            throw new \RuntimeException("Tutor id is required.");
        }

        return $this->serviceRepository->getAllByTutorId($tutorId);
    }

    public function getAllActiveServices(): array
    {
        return $this->serviceRepository->getAllActive();
    }

    public function getService(int $id): ?ServiceModel
    {
        return $this->serviceRepository->find($id);
    }

    public function createService(
        int $tutorId,
        string $title,
        ?string $description,
        int $durationMinutes,
        float $price
    ): int {
        if ($tutorId <= 0) {
            throw new \RuntimeException("Tutor id is required.");
        }

        $tutor = $this->userRepository->findById($tutorId);
        if (!$tutor || !$tutor->isTutor()) {
            throw new \RuntimeException("Service owner must be a tutor.");
        }

        $title = trim($title);
        if ($title === '') {
            throw new \RuntimeException("Service title is required.");
        }

        if ($durationMinutes <= 0) {
            throw new \RuntimeException("Duration must be greater than 0.");
        }

        if ($price <= 0) {
            throw new \RuntimeException("Price must be greater than 0.");
        }

        $service = new ServiceModel(
            id: null,
            tutorId: $tutorId,
            tutorName: $tutor->name,
            title: $title,
            description: $description ? trim($description) : null,
            durationMinutes: $durationMinutes,
            price: $price,
            isActive: true,
            createdAt: null
        );

        return $this->serviceRepository->create($service);
    }

    public function updateService(int $id, string $title, ?string $description, int $durationMinutes, float $price): void
    {
        $service = $this->serviceRepository->find($id);
        if (!$service) {
            throw new \RuntimeException("Service not found.");
        }

        if ($price <= 0) {
            throw new \RuntimeException("Price must be greater than 0.");
        }

        $service->title = trim($title);
        $service->description = $description ? trim($description) : null;
        $service->durationMinutes = $durationMinutes;
        $service->price = $price;

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
