<?php

// Keep API responses as valid JSON even when PHP emits notices/deprecations.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require __DIR__ . '/../vendor/autoload.php';

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

// CORS headers for localhost requests
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('/^https?:\/\/(localhost|127\.0\.0\.1|::1)(:\d+)?$/', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dispatcher = simpleDispatcher(function (RouteCollector $r) {
    $r->addRoute('GET', '/api/health', ['App\\Controllers\\Api\\HealthApiController', 'index']);
    $r->addRoute('POST', '/api/register', ['App\\Controllers\\Api\\AuthApiController', 'register']);
    $r->addRoute('POST', '/auth/login', ['App\\Controllers\\Api\\AuthApiController', 'login']);
    $r->addRoute('POST', '/auth/refresh', ['App\\Controllers\\Api\\AuthApiController', 'refresh']);
    $r->addRoute('POST', '/auth/logout', ['App\\Controllers\\Api\\AuthApiController', 'logout']);
    $r->addRoute('GET', '/auth/me', ['App\\Controllers\\Api\\AuthApiController', 'currentUser']);

    // Student service browsing
    $r->addRoute('GET', '/api/student/services', ['App\\Controllers\\Api\\ServiceApiController', 'studentServices']);
    $r->addRoute('GET', '/api/student/services/{id:\\d+}/timeslots', ['App\\Controllers\\Api\\ServiceApiController', 'studentTimeslots']);
    $r->addRoute('GET', '/api/student/bookings', ['App\\Controllers\\Api\\BookingApiController', 'studentBookings']);
    $r->addRoute('GET', '/api/student/bookings/upcoming-count', ['App\\Controllers\\Api\\BookingApiController', 'upcomingCount']);
    $r->addRoute('DELETE', '/api/student/bookings/{id:\\d+}', ['App\\Controllers\\Api\\BookingApiController', 'cancelBooking']);
    $r->addRoute('GET', '/api/student/bookings/{id:\\d+}/reschedule-options', ['App\\Controllers\\Api\\BookingApiController', 'rescheduleOptions']);
    $r->addRoute('PUT', '/api/student/bookings/{id:\\d+}/reschedule', ['App\\Controllers\\Api\\BookingApiController', 'rescheduleBooking']);
    $r->addRoute('POST', '/api/student/bookings/checkout-session', ['App\\Controllers\\Api\\BookingApiController', 'createCheckoutSession']);
    $r->addRoute('POST', '/api/stripe/webhook', ['App\\Controllers\\Api\\BookingApiController', 'stripeWebhook']);

    // Tutor service CRUD (owned services only)
    $r->addRoute('GET', '/api/tutor/services', ['App\\Controllers\\Api\\ServiceApiController', 'tutorServices']);
    $r->addRoute('GET', '/api/tutor/services/{id:\\d+}', ['App\\Controllers\\Api\\ServiceApiController', 'tutorService']);
    $r->addRoute('POST', '/api/tutor/services', ['App\\Controllers\\Api\\ServiceApiController', 'tutorCreateService']);
    $r->addRoute('PUT', '/api/tutor/services/{id:\\d+}', ['App\\Controllers\\Api\\ServiceApiController', 'tutorUpdateService']);
    $r->addRoute('DELETE', '/api/tutor/services/{id:\\d+}', ['App\\Controllers\\Api\\ServiceApiController', 'tutorDeleteService']);
    $r->addRoute('GET', '/api/tutor/services/{id:\\d+}/timeslots', ['App\\Controllers\\Api\\ServiceApiController', 'tutorServiceTimeslots']);
    $r->addRoute('POST', '/api/tutor/services/{id:\\d+}/timeslots', ['App\\Controllers\\Api\\ServiceApiController', 'tutorCreateTimeslot']);
    $r->addRoute('PUT', '/api/tutor/timeslots/{id:\\d+}', ['App\\Controllers\\Api\\ServiceApiController', 'tutorUpdateTimeslot']);
    $r->addRoute('DELETE', '/api/tutor/timeslots/{id:\\d+}', ['App\\Controllers\\Api\\ServiceApiController', 'tutorDeleteTimeslot']);

    $r->addRoute('GET', '/api/admin/bookings/paid', ['App\\Controllers\\Api\\BookingApiController', 'paid']);
});

$httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Not Found']);
        break;

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Method Not Allowed']);
        break;

    case FastRoute\Dispatcher::FOUND:
        [$class, $method] = $routeInfo[1];
        $vars = $routeInfo[2];

        $controller = new $class();
        $controller->$method($vars);
        break;
}
