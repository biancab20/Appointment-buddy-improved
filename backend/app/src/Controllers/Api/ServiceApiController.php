<?php

namespace App\Controllers\Api;

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

    // GET /api/services  (active only)
    public function index(): void
    {
        $services = $this->serviceService->getAllActiveServices();

        $this->json([
            'services' => array_map(fn($s) => $s->toArray(), $services)
        ]);
    }

    // GET /api/services/{id}/timeslots  (available only)
    public function timeslots(array $params): void
    {
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
}
