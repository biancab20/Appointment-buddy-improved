<?php

namespace App\Services\Interfaces;

use App\Models\UserModel;

interface IUserService
{
    public function registerStudent(string $name, string $email, string $password): int;

    public function login(string $email, string $password): UserModel;
}
