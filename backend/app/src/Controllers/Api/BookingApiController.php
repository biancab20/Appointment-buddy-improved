<?php

namespace App\Controllers\Api;

use App\Services\Interfaces\IBookingService;
use App\Services\BookingService;

class BookingApiController extends ApiBaseController
{
    private IBookingService $bookingService;

    public function __construct()
    {
        $this->bookingService = new BookingService();
    }

    // GET /api/admin/bookings/pending
    public function pending(): void
    {
        $this->requireAdmin();

        $all = $this->bookingService->getAllBookings();
        $pending = array_values(array_filter($all, fn($b) => ($b['status'] ?? '') === 'pending'));

        $this->json([
            'pending' => $pending
        ]);
    }
}
