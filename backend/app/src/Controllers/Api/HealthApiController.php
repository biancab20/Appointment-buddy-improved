<?php

namespace App\Controllers\Api;

class HealthApiController extends ApiBaseController
{
    public function index(): void
    {
        $this->json([
            'status' => 'ok',
            'service' => 'appointment_buddy_improved_backend'
        ]);
    }
}
