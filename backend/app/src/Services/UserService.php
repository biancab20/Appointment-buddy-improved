<?php

namespace App\Services;

use App\Models\UserModel;
use App\Repositories\Interfaces\IUserRepository;
use App\Repositories\UserRepository;
use App\Services\Interfaces\IUserService;

class UserService implements IUserService
{
    private IUserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    public function registerUser(string $name, string $email, string $password, string $role): int
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        $password = (string)$password;
        $role = strtolower(trim($role));

        if ($name === '') {
            throw new \RuntimeException("Name is required.");
        }

        if ($email === '') {
            throw new \RuntimeException("Email is required.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException("Invalid email address.");
        }

        if (strlen($password) < 6) {
            throw new \RuntimeException("Password must be at least 6 characters.");
        }

        if ($role === '') {
            throw new \RuntimeException("Role is required.");
        }

        if (!UserModel::isAllowedRole($role)) {
            throw new \RuntimeException("Invalid role. Allowed values: tutor, student.");
        }

        // Prevent duplicate email
        if ($this->userRepository->findByEmail($email)) {
            throw new \RuntimeException("An account with this email already exists.");
        }

        $user = new UserModel(
            id: null,
            name: $name,
            email: $email,
            passwordHash: password_hash($password, PASSWORD_DEFAULT),
            role: $role,
            createdAt: null
        );

        return $this->userRepository->create($user);
    }

    public function login(string $email, string $password): UserModel
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException("Invalid email or password.");
        }

        $user = $this->userRepository->findByEmail($email);

        if (!$user || !password_verify($password, $user->passwordHash)) {
            throw new \RuntimeException("Invalid email or password.");
        }

        return $user;
    }
}
