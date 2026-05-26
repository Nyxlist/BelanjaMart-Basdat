<?php
/**
 * Tiny front-controller for /backend/api/v1/?route=...
 *
 *  Auth    : POST /v1/?route=auth/login
 *            POST /v1/?route=auth/register
 *  Products: GET  /v1/?route=products
 *            GET  /v1/?route=products/{id}
 *  Cart    : GET/POST /v1/?route=cart
 *  Chat    : GET/POST /v1/?route=chat
 *  Orders  : GET/POST /v1/?route=orders
 *  Currency: GET  /v1/?route=currency
 *
 * Each sub-script handles its own auth & method dispatch.
 */
require_once __DIR__ . '/../../config/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    exit;
}

RateLimitMiddleware::enforce('api', config('rate_limit.api_per_minute', 120));

$route = trim((string) ($_GET['route'] ?? ''), '/');
if ($route === '') {
    Response::ok(['name' => 'BelanjaMart API', 'version' => 'v1']);
}

$map = [
    'auth/login'    => 'auth_login.php',
    'auth/register' => 'auth_register.php',
    'auth/me'       => 'auth_me.php',
    'products'      => 'products.php',
    'cart'          => 'cart.php',
    'orders'        => 'orders.php',
    'chat'          => 'chat.php',
    'currency'      => 'currency.php',
    'recommendations' => 'recommendations.php',
];

// /products/{id} -> products.php with $_GET['id']
if (preg_match('#^products/(\d+)$#', $route, $m)) {
    $_GET['id'] = (int) $m[1];
    require __DIR__ . '/products.php';
    exit;
}

if (!isset($map[$route])) {
    Response::notFound('Unknown route');
}
require __DIR__ . '/' . $map[$route];
