<?php

namespace App\Repositories\Interfaces;

use App\Models\UserModel;

interface IUserRepository
{
    public function create(UserModel $user): int;

    public function findByEmail(string $email): ?UserModel;

    public function findById(int $id): ?UserModel;
}
