<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Repositories\BookingRepository;
use App\Repositories\Interfaces\IBookingRepository;
use App\Repositories\Interfaces\IServiceRepository;
use App\Repositories\Interfaces\ITransactionRepository;
use App\Repositories\Interfaces\IUserRepository;
use App\Repositories\ServiceRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;

class AdminApiController extends ApiBaseController
{
    private IUserRepository $userRepository;
    private IServiceRepository $serviceRepository;
    private IBookingRepository $bookingRepository;
    private ITransactionRepository $transactionRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->serviceRepository = new ServiceRepository();
        $this->bookingRepository = new BookingRepository();
        $this->transactionRepository = new TransactionRepository();
    }

    // GET /api/admin/users
    public function users(): void
    {
        $this->requireAdmin();

        try {
            $page = $this->readRequiredIntQuery('page', 1, 1);
            $perPage = min($this->readRequiredIntQuery('per_page', 10, 1), 100);

            $role = strtolower(trim((string)($_GET['role'] ?? '')));
            if ($role !== '' && !UserModel::isAllowedRole($role)) {
                throw new \RuntimeException('Invalid role filter.');
            }

            $search = trim((string)($_GET['search'] ?? ''));

            $result = $this->userRepository->getPaginated([
                'role' => $role,
                'search' => $search,
            ], $page, $perPage);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        }

        $items = array_map(function (UserModel $user): array {
            $row = $user->toArray();
            unset($row['password_hash']);
            return $row;
        }, $result['items'] ?? []);

        $total = (int)($result['total'] ?? 0);

        $this->json([
            'users' => $items,
            'pagination' => $this->buildPagination($page, $perPage, $total),
            'filters' => [
                'role' => $role !== '' ? $role : null,
                'search' => $search !== '' ? $search : null,
            ],
        ]);
    }

    // GET /api/admin/services
    public function services(): void
    {
        $this->requireAdmin();

        try {
            $page = $this->readRequiredIntQuery('page', 1, 1);
            $perPage = min($this->readRequiredIntQuery('per_page', 10, 1), 100);

            $subject = trim((string)($_GET['subject'] ?? ''));
            $tutorId = $this->readOptionalIntQuery('tutor_id', 1);
            $isActive = $this->readOptionalBoolQuery('is_active');
            $minDuration = $this->readOptionalIntQuery('min_duration', 1);
            $maxDuration = $this->readOptionalIntQuery('max_duration', 1);
            $minPrice = $this->readOptionalFloatQuery('min_price', 0);
            $maxPrice = $this->readOptionalFloatQuery('max_price', 0);

            if ($minDuration !== null && $maxDuration !== null && $minDuration > $maxDuration) {
                throw new \RuntimeException('Min duration cannot be greater than max duration.');
            }

            if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
                throw new \RuntimeException('Min price cannot be greater than max price.');
            }

            $result = $this->serviceRepository->getAllPaginated([
                'subject' => $subject,
                'tutor_id' => $tutorId,
                'is_active' => $isActive,
                'min_duration' => $minDuration,
                'max_duration' => $maxDuration,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
            ], $page, $perPage);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        }

        $services = array_map(fn($service) => $service->toArray(), $result['items'] ?? []);
        $total = (int)($result['total'] ?? 0);

        $this->json([
            'services' => $services,
            'pagination' => $this->buildPagination($page, $perPage, $total),
            'filters' => [
                'subject' => $subject !== '' ? $subject : null,
                'tutor_id' => $tutorId,
                'is_active' => $isActive,
                'min_duration' => $minDuration,
                'max_duration' => $maxDuration,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
            ],
        ]);
    }

    // GET /api/admin/bookings
    public function bookings(): void
    {
        $this->requireAdmin();

        try {
            $page = $this->readRequiredIntQuery('page', 1, 1);
            $perPage = min($this->readRequiredIntQuery('per_page', 10, 1), 100);

            $scope = strtolower(trim((string)($_GET['scope'] ?? '')));
            if ($scope !== '' && !in_array($scope, ['upcoming', 'history'], true)) {
                throw new \RuntimeException('Invalid scope value.');
            }

            $status = strtolower(trim((string)($_GET['status'] ?? '')));
            if ($status !== '' && !in_array($status, ['paid', 'cancelled'], true)) {
                throw new \RuntimeException('Invalid status value.');
            }

            $studentId = $this->readOptionalIntQuery('student_id', 1);
            $tutorId = $this->readOptionalIntQuery('tutor_id', 1);
            $serviceId = $this->readOptionalIntQuery('service_id', 1);
            $dateFrom = $this->readOptionalDateQuery('date_from');
            $dateTo = $this->readOptionalDateQuery('date_to');

            if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
                throw new \RuntimeException('Date from cannot be greater than date to.');
            }

            $result = $this->bookingRepository->getAllPaginated([
                'scope' => $scope,
                'status' => $status,
                'student_id' => $studentId,
                'tutor_id' => $tutorId,
                'service_id' => $serviceId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ], $page, $perPage);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        }

        $total = (int)($result['total'] ?? 0);

        $this->json([
            'bookings' => $result['items'] ?? [],
            'pagination' => $this->buildPagination($page, $perPage, $total),
            'filters' => [
                'scope' => $scope !== '' ? $scope : null,
                'status' => $status !== '' ? $status : null,
                'student_id' => $studentId,
                'tutor_id' => $tutorId,
                'service_id' => $serviceId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    // GET /api/admin/transactions
    public function transactions(): void
    {
        $this->requireAdmin();

        try {
            $page = $this->readRequiredIntQuery('page', 1, 1);
            $perPage = min($this->readRequiredIntQuery('per_page', 10, 1), 100);

            $status = strtolower(trim((string)($_GET['status'] ?? '')));
            if ($status !== '' && !in_array($status, ['pending', 'paid', 'failed', 'cancelled'], true)) {
                throw new \RuntimeException('Invalid status value.');
            }

            $provider = strtolower(trim((string)($_GET['provider'] ?? '')));
            if ($provider !== '' && $provider !== 'stripe') {
                throw new \RuntimeException('Invalid provider value.');
            }

            $currency = strtolower(trim((string)($_GET['currency'] ?? '')));
            if ($currency !== '' && !preg_match('/^[a-z]{3,10}$/', $currency)) {
                throw new \RuntimeException('Invalid currency value.');
            }

            $studentId = $this->readOptionalIntQuery('student_id', 1);
            $tutorId = $this->readOptionalIntQuery('tutor_id', 1);
            $serviceId = $this->readOptionalIntQuery('service_id', 1);
            $timeslotId = $this->readOptionalIntQuery('timeslot_id', 1);
            $bookingId = $this->readOptionalIntQuery('booking_id', 1);
            $dateFrom = $this->readOptionalDateQuery('date_from');
            $dateTo = $this->readOptionalDateQuery('date_to');

            if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
                throw new \RuntimeException('Date from cannot be greater than date to.');
            }

            $result = $this->transactionRepository->getPaginated([
                'status' => $status,
                'provider' => $provider,
                'currency' => $currency,
                'student_id' => $studentId,
                'tutor_id' => $tutorId,
                'service_id' => $serviceId,
                'timeslot_id' => $timeslotId,
                'booking_id' => $bookingId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ], $page, $perPage);
        } catch (\RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 400);
            return;
        }

        $total = (int)($result['total'] ?? 0);

        $this->json([
            'transactions' => $result['items'] ?? [],
            'pagination' => $this->buildPagination($page, $perPage, $total),
            'filters' => [
                'status' => $status !== '' ? $status : null,
                'provider' => $provider !== '' ? $provider : null,
                'currency' => $currency !== '' ? $currency : null,
                'student_id' => $studentId,
                'tutor_id' => $tutorId,
                'service_id' => $serviceId,
                'timeslot_id' => $timeslotId,
                'booking_id' => $bookingId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    private function buildPagination(int $page, int $perPage, int $total): array
    {
        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
        ];
    }

    private function readRequiredIntQuery(string $key, int $default, int $min): int
    {
        $rawValue = $_GET[$key] ?? null;
        if ($rawValue === null || $rawValue === '') {
            return $default;
        }

        $value = filter_var($rawValue, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min) {
            throw new \RuntimeException("Invalid {$key} value.");
        }

        return (int)$value;
    }

    private function readOptionalIntQuery(string $key, int $min): ?int
    {
        $rawValue = $_GET[$key] ?? null;
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        $value = filter_var($rawValue, FILTER_VALIDATE_INT);
        if ($value === false || $value < $min) {
            throw new \RuntimeException("Invalid {$key} value.");
        }

        return (int)$value;
    }

    private function readOptionalFloatQuery(string $key, float $min): ?float
    {
        $rawValue = $_GET[$key] ?? null;
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        $value = filter_var($rawValue, FILTER_VALIDATE_FLOAT);
        if ($value === false || (float)$value < $min) {
            throw new \RuntimeException("Invalid {$key} value.");
        }

        return (float)$value;
    }

    private function readOptionalDateQuery(string $key): ?string
    {
        $rawValue = trim((string)($_GET[$key] ?? ''));
        if ($rawValue === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawValue)) {
            throw new \RuntimeException("Invalid {$key} value.");
        }

        [$year, $month, $day] = array_map('intval', explode('-', $rawValue));
        if (!checkdate($month, $day, $year)) {
            throw new \RuntimeException("Invalid {$key} value.");
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function readOptionalBoolQuery(string $key): ?bool
    {
        $rawValue = $_GET[$key] ?? null;
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        $normalized = strtolower(trim((string)$rawValue));
        if (in_array($normalized, ['1', 'true', 'yes'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no'], true)) {
            return false;
        }

        throw new \RuntimeException("Invalid {$key} value.");
    }
}
