<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Security\JwtService;

abstract class ApiBaseController
{
    protected ?array $authUser = null;

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function requireAuth(): void
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if ($header === '') {
            $this->json(['error' => 'Unauthorized'], 401);
            exit;
        }

        if (!preg_match('/^\s*Bearer\s+(.+)\s*$/i', $header, $matches)) {
            $this->json(['error' => 'Unauthorized'], 401);
            exit;
        }

        try {
            $jwt = new JwtService();
            $payload = $jwt->decodeAccessToken($matches[1]);
        } catch (\RuntimeException $e) {
            $this->json(['error' => 'Unauthorized'], 401);
            exit;
        }

        $userData = $payload['data'] ?? null;
        if (!is_array($userData)) {
            $this->json(['error' => 'Unauthorized'], 401);
            exit;
        }

        $userId = (int)($userData['id'] ?? 0);
        $role = (string)($userData['role'] ?? '');

        if ($userId <= 0 || !UserModel::isAllowedRole($role)) {
            $this->json(['error' => 'Unauthorized'], 401);
            exit;
        }

        $this->authUser = [
            'id' => $userId,
            'role' => $role,
            'email' => (string)($userData['email'] ?? ''),
            'name' => (string)($userData['name'] ?? ''),
        ];
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();

        $role = (string)($this->authUser['role'] ?? '');
        if ($role !== UserModel::ROLE_ADMIN) {
            $this->json(['error' => 'Forbidden'], 403);
            exit;
        }
    }

    protected function authUserId(): int
    {
        return (int)($this->authUser['id'] ?? 0);
    }

    protected function authUserRole(): string
    {
        return (string)($this->authUser['role'] ?? '');
    }
}
