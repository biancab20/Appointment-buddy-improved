<?php

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
    $r->addRoute('POST', '/auth/login', ['App\\Controllers\\Api\\AuthApiController', 'login']);
    $r->addRoute('POST', '/auth/refresh', ['App\\Controllers\\Api\\AuthApiController', 'refresh']);
    $r->addRoute('POST', '/auth/logout', ['App\\Controllers\\Api\\AuthApiController', 'logout']);
    $r->addRoute('GET', '/auth/me', ['App\\Controllers\\Api\\AuthApiController', 'currentUser']);

    $r->addRoute('GET', '/api/services', ['App\\Controllers\\Api\\ServiceApiController', 'index']);
    $r->addRoute('GET', '/api/services/{id:\\d+}/timeslots', ['App\\Controllers\\Api\\ServiceApiController', 'timeslots']);

    $r->addRoute('GET', '/api/admin/bookings/pending', ['App\\Controllers\\Api\\BookingApiController', 'pending']);
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