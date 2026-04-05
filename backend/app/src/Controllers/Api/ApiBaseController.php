<?php

namespace App\Controllers\Api;

abstract class ApiBaseController
{
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    protected function requireAuth(): void
    {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['error' => 'Unauthorized'], 401);
            exit;
        }
    }

    protected function requireAdmin(): void
    {
        $this->requireAuth();
        $role = $_SESSION['role'] ?? null;
        if ($role !== 'admin') {
            $this->json(['error' => 'Forbidden'], 403);
            exit;
        }
    }
}
