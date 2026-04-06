<?php

namespace App\Services\Interfaces;

use App\Models\TimeslotModel;

interface ITimeslotService
{
    /** @return TimeslotModel[] */
    public function getUpcomingTimeslotsForService(int $serviceId): array;

    public function getTimeslot(int $timeslotId): ?TimeslotModel;

    public function countUpcomingAvailableForService(int $serviceId): int;

    public function deactivateTimeslot(int $timeslotId): int;

    /** @return TimeslotModel[] */
    public function getAllForService(int $serviceId): array;

    public function createForService(int $serviceId, string $start): int;

    public function updateTimeslot(int $timeslotId, string $start): void;
    public function activateTimeslot(int $timeslotId): void;
}
