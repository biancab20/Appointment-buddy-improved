<?php

namespace App\Services\Interfaces;

use App\Models\UserModel;

interface IUserService
{
    public function registerUser(string $name, string $email, string $password, string $role): int;

    public function login(string $email, string $password): UserModel;
}
