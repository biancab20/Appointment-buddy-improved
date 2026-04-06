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

    // GET /api/admin/bookings/paid
    public function paid(): void
    {
        $this->requireAdmin();

        $all = $this->bookingService->getAllBookings();
        $paid = array_values(array_filter($all, fn($b) => ($b['status'] ?? '') === 'paid'));

        $this->json([
            'paid' => $paid
        ]);
    }
}
