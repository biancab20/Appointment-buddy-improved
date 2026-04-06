<?php

namespace App\Services;

use App\Models\TimeslotModel;
use App\Repositories\BookingRepository;
use App\Repositories\Interfaces\IBookingRepository;
use App\Repositories\Interfaces\IServiceRepository;
use App\Repositories\Interfaces\ITimeslotRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\TimeslotRepository;
use App\Services\Interfaces\ITimeslotService;

class TimeslotService implements ITimeslotService
{
    private ITimeslotRepository $timeslotRepository;
    private IBookingRepository $bookingRepository;
    private IServiceRepository $serviceRepository;
    public function __construct()
    {
        $this->timeslotRepository = new TimeslotRepository();
        $this->bookingRepository = new BookingRepository();
        $this->serviceRepository = new ServiceRepository();
    }

    public function getAllForService(int $serviceId): array
    {
        return $this->timeslotRepository->getAllForService($serviceId);
    }

    public function getTimeslot(int $timeslotId): ?TimeslotModel
    {
        if ($timeslotId <= 0) {
            return null;
        }

        return $this->timeslotRepository->find($timeslotId);
    }

    public function createForService(int $serviceId, string $start, string $end): int
    {
        $startDt = $this->parseDateTimeLocal($start);
        $endDt = $this->parseDateTimeLocal($end);
        $now = new \DateTimeImmutable('now');

        if ($startDt <= $now)
            throw new \RuntimeException("Start time must be in the future.");
        if ($endDt <= $startDt)
            throw new \RuntimeException("End time must be after start time.");

        $model = new TimeslotModel(
            id: null,
            serviceId: $serviceId,
            startTime: $startDt->format('Y-m-d H:i:s'),
            endTime: $endDt->format('Y-m-d H:i:s'),
            isActive: true
        );

        return $this->timeslotRepository->create($model);
    }

    public function updateTimeslot(int $timeslotId, string $start, string $end): void
    {
        // block update if a paid booking exists
        if ($this->bookingRepository->countActiveForTimeslot($timeslotId) > 0) {
            throw new \RuntimeException("Cannot update: this timeslot has a paid booking.");
        }

        $existing = $this->timeslotRepository->find($timeslotId);
        if (!$existing)
            throw new \RuntimeException("Timeslot not found.");

        $startDt = $this->parseDateTimeLocal($start);
        $endDt = $this->parseDateTimeLocal($end);
        $now = new \DateTimeImmutable('now');

        if ($startDt <= $now)
            throw new \RuntimeException("Start time must be in the future.");
        if ($endDt <= $startDt)
            throw new \RuntimeException("End time must be after start time.");

        $existing->startTime = $startDt->format('Y-m-d H:i:s');
        $existing->endTime = $endDt->format('Y-m-d H:i:s');

        $this->timeslotRepository->update($existing);
    }

    public function getUpcomingTimeslotsForService(int $serviceId): array
    {
        return $this->timeslotRepository->getUpcomingForService($serviceId);
    }

    public function deactivateTimeslot(int $timeslotId): int
    {
        // If paid bookings exist: cancel them.
        $cancelledCount = $this->bookingRepository->cancelPaidForTimeslot($timeslotId);

        $this->timeslotRepository->setActive($timeslotId, false);

        return $cancelledCount;
    }

    public function activateTimeslot(int $timeslotId): void
    {
        $timeslot = $this->timeslotRepository->find($timeslotId);
        if (!$timeslot) {
            throw new \RuntimeException("Timeslot not found.");
        }

        // only allow if service is active
        if (!$this->serviceRepository->isActive($timeslot->serviceId)) {
            throw new \RuntimeException("Cannot activate timeslot because the service is inactive.");
        }

        $this->timeslotRepository->setActive($timeslotId, true);
    }

    public function countUpcomingAvailableForService(int $serviceId): int
    {
        return $this->timeslotRepository->countUpcomingAvailableForService($serviceId);
    }

    private function parseDateTimeLocal(string $value): \DateTimeImmutable
    {
        // from <input type="datetime-local"> => "YYYY-MM-DDTHH:MM"
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        if (!$dt) {
            throw new \RuntimeException("Invalid date/time format.");
        }
        return $dt;
    }
}
