<?php

namespace App\Controllers\Api;

use App\Models\ServiceModel;
use App\Models\TimeslotModel;
use App\Services\Interfaces\IServiceCatalogService;
use App\Services\ServiceCatalogService;
use App\Services\Interfaces\ITimeslotService;
use App\Services\TimeslotService;

class ServiceApiController extends ApiBaseController
{
    private IServiceCatalogService $serviceService;
    private ITimeslotService $timeslotService;

    public function __construct()
    {
        $this->serviceService = new ServiceCatalogService();
        $this->timeslotService = new TimeslotService();
    }

    // GET /api/student/services
    public function studentServices(): void
    {
        $this->requireStudent();

        $services = $this->serviceService->getAllActiveServices();

        $this->json([
            'services' => array_map(fn($s) => $s->toArray(), $services)
        ]);
    }

    // GET /api/student/services/{id}/timeslots
    public function studentTimeslots(array $params): void
    {
        $this->requireStudent();

        $serviceId = (int)($params['id'] ?? 0);
        if ($serviceId <= 0) {
            $this->json(['error' => 'Invalid service id'], 400);
            return;
        }

        $service = $this->serviceService->getService($serviceId);
        if (!$service || !$service->isActive) {
            $this->json(['error' => 'Service not found'], 404);
            return;
        }

        $timeslots = $this->timeslotService->getUpcomingTimeslotsForService($serviceId);

        $this->json([
            'service' => $service->toArray(),
            'timeslots' => array_map(fn($t) => $t->toArray(), $timeslots),
        ]);
    }

    // GET /api/tutor/services
    public function tutorServices(): void
    {
        $this->requireTutor();

        $services = $this->serviceService->getServicesByTutorId($this->authUserId());

        $this->json([
            'services' => array_map(fn($s) => $s->toArray(), $services),
        ]);
    }

    // GET /api/tutor/services/{id}
    public function tutorService(array $params): void
    {
        $this->requireTutor();

        $serviceId = (int)($params['id'] ?? 0);
        if ($serviceId <= 0) {
            $this->json(['error' => 'Invalid service id'], 400);
            return;
        }

        $service = $this->serviceService->getService($serviceId);
        if (!$service) {
            $this->json(['error' => 'Service not found'], 404);
            return;
        }

        if ($service->tutorId !== $this->authUserId()) {
            $this->json(['error' => 'Forbidden'], 403);
            return;
        }

        $this->json([
            'service' => $service->toArray(),
        ]);
    }

    // POST /api/tutor/services
    public function tutorCreateService(): void
    {
        $this->requireTutor();

        $payload = $this->readJsonBody();
        $title = (string)($payload['title'] ?? '');
        $description = isset($payload['description']) ? (string)$payload['description'] : null;
        $durationMinutes = (int)($payload['duration_minutes'] ?? 0);
        $price = (float)($payload['price'] ?? 0);

        try {
            $serviceId = $this->serviceService->createService(
                $this->authUserId(),
                $title,
                $description,
                $durationMinutes,
                $price
            );

            $service = $this->serviceService->getService($serviceId);
            $this->json([
                'service' => $service ? $service->toArray() : null,
            ], 201);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    // PUT /api/tutor/services/{id}
    public function tutorUpdateService(array $params): void
    {
        $this->requireTutor();

        $serviceId = (int)($params['id'] ?? 0);
        if ($serviceId <= 0) {
            $this->json(['error' => 'Invalid service id'], 400);
            return;
        }

        $service = $this->serviceService->getService($serviceId);
        if (!$service) {
            $this->json(['error' => 'Service not found'], 404);
            return;
        }

        if ($service->tutorId !== $this->authUserId()) {
            $this->json(['error' => 'Forbidden'], 403);
            return;
        }

        $payload = $this->readJsonBody();
        $title = (string)($payload['title'] ?? '');
        $description = isset($payload['description']) ? (string)$payload['description'] : null;
        $durationMinutes = (int)($payload['duration_minutes'] ?? 0);
        $price = (float)($payload['price'] ?? 0);

        try {
            $this->serviceService->updateService($serviceId, $title, $description, $durationMinutes, $price);
            $updated = $this->serviceService->getService($serviceId);

            $this->json([
                'service' => $updated ? $updated->toArray() : null,
            ]);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    // DELETE /api/tutor/services/{id}
    public function tutorDeleteService(array $params): void
    {
        $this->requireTutor();

        $serviceId = (int)($params['id'] ?? 0);
        if ($serviceId <= 0) {
            $this->json(['error' => 'Invalid service id'], 400);
            return;
        }

        $service = $this->serviceService->getService($serviceId);
        if (!$service) {
            $this->json(['error' => 'Service not found'], 404);
            return;
        }

        if ($service->tutorId !== $this->authUserId()) {
            $this->json(['error' => 'Forbidden'], 403);
            return;
        }

        $this->serviceService->deactivateService($serviceId);
        $this->json([
            'message' => 'Service deactivated successfully.',
        ]);
    }

    // GET /api/tutor/services/{id}/timeslots
    public function tutorServiceTimeslots(array $params): void
    {
        $this->requireTutor();

        $service = $this->resolveTutorOwnedService((int)($params['id'] ?? 0));
        if (!$service) {
            return;
        }

        $timeslots = $this->timeslotService->getAllForService((int)$service->id);

        $this->json([
            'service' => $service->toArray(),
            'timeslots' => array_map(fn($t) => $t->toArray(), $timeslots),
        ]);
    }

    // POST /api/tutor/services/{id}/timeslots
    public function tutorCreateTimeslot(array $params): void
    {
        $this->requireTutor();

        $service = $this->resolveTutorOwnedService((int)($params['id'] ?? 0));
        if (!$service) {
            return;
        }

        if (!$service->isActive) {
            $this->json(['error' => 'Cannot create timeslot for an inactive service.'], 400);
            return;
        }

        $payload = $this->readJsonBody();
        $start = (string)($payload['start_time'] ?? '');

        try {
            $timeslotId = $this->timeslotService->createForService((int)$service->id, $start);
            $timeslot = $this->timeslotService->getTimeslot($timeslotId);

            $this->json([
                'timeslot' => $timeslot ? $timeslot->toArray() : null,
            ], 201);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    // PUT /api/tutor/timeslots/{id}
    public function tutorUpdateTimeslot(array $params): void
    {
        $this->requireTutor();

        $timeslot = $this->resolveTutorOwnedTimeslot((int)($params['id'] ?? 0));
        if (!$timeslot) {
            return;
        }

        $payload = $this->readJsonBody();
        $start = (string)($payload['start_time'] ?? '');

        try {
            $this->timeslotService->updateTimeslot((int)$timeslot->id, $start);
            $updated = $this->timeslotService->getTimeslot((int)$timeslot->id);

            $this->json([
                'timeslot' => $updated ? $updated->toArray() : null,
            ]);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    // DELETE /api/tutor/timeslots/{id}
    public function tutorDeleteTimeslot(array $params): void
    {
        $this->requireTutor();

        $timeslot = $this->resolveTutorOwnedTimeslot((int)($params['id'] ?? 0));
        if (!$timeslot) {
            return;
        }

        $cancelledBookingsCount = $this->timeslotService->deactivateTimeslot((int)$timeslot->id);

        $this->json([
            'message' => 'Timeslot deactivated successfully.',
            'cancelled_bookings_count' => $cancelledBookingsCount,
        ]);
    }

    private function resolveTutorOwnedService(int $serviceId): ?ServiceModel
    {
        if ($serviceId <= 0) {
            $this->json(['error' => 'Invalid service id'], 400);
            return null;
        }

        $service = $this->serviceService->getService($serviceId);
        if (!$service) {
            $this->json(['error' => 'Service not found'], 404);
            return null;
        }

        if ($service->tutorId !== $this->authUserId()) {
            $this->json(['error' => 'Forbidden'], 403);
            return null;
        }

        return $service;
    }

    private function resolveTutorOwnedTimeslot(int $timeslotId): ?TimeslotModel
    {
        if ($timeslotId <= 0) {
            $this->json(['error' => 'Invalid timeslot id'], 400);
            return null;
        }

        $timeslot = $this->timeslotService->getTimeslot($timeslotId);
        if (!$timeslot) {
            $this->json(['error' => 'Timeslot not found'], 404);
            return null;
        }

        $service = $this->serviceService->getService($timeslot->serviceId);
        if (!$service) {
            $this->json(['error' => 'Service not found'], 404);
            return null;
        }

        if ($service->tutorId !== $this->authUserId()) {
            $this->json(['error' => 'Forbidden'], 403);
            return null;
        }

        return $timeslot;
    }

}
