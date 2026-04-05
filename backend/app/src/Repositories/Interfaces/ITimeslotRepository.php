<?php

namespace App\Repositories\Interfaces;

use App\Models\TimeslotModel;

interface ITimeslotRepository
{
    /** Admin actions: Create a new timeslot for service*/
    public function create(TimeslotModel $timeslot): int;

    /** Helper: Get by id*/
    public function find(int $id): ?TimeslotModel;

    /** Helper: Get upcoming timeslots for a specific service*/
    /** @return TimeslotModel[] */
    public function getUpcomingForService(int $serviceId): array;

    /** Helper: Set timeslot as active*/
    public function setActive(int $timeslotId, bool $isActive): bool;

    /** Helper: Count upcoming timeslots available (active) for a specific service*/
    public function countUpcomingAvailableForService(int $serviceId): int;

    /** Helper: Get the service id for a specific timeslot*/
    public function getServiceIdForTimeslot(int $timeslotId): ?int;

    /** Admin action: Get all timeslots for a service*/
    /** @return TimeslotModel[] */
    public function getAllForService(int $serviceId): array;

    /** Admin action: Update a timeslot*/
    public function update(TimeslotModel $timeslot): bool;
}
