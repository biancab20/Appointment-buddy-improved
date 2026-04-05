<?php

require __DIR__ . '/../vendor/autoload.php';
session_start();

use FastRoute\RouteCollector;
use function FastRoute\simpleDispatcher;

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$dispatcher = simpleDispatcher(function (RouteCollector $r) {
    $r->addRoute('GET', '/api/health', ['App\\Controllers\\Api\\HealthApiController', 'index']);

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
