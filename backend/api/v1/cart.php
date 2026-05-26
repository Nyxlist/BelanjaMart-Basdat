<?php
/**
 * GET  /api/v1/?route=cart        list cart items
 * POST /api/v1/?route=cart        body: { action: add|remove|update|clear, ... }
 */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    Response::ok([
        'items'  => CartService::items(),
        'totals' => CartService::totals(),
    ]);
}

$body   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $body['action'] ?? '';

switch ($action) {
    case 'add':
        $r = CartService::add((int) ($body['product_id'] ?? 0), (int) ($body['qty'] ?? 1));
        if (!$r['ok']) Response::error($r['message']);
        break;
    case 'update':
        CartService::update((array) ($body['quantities'] ?? []));
        break;
    case 'remove':
        CartService::remove((int) ($body['product_id'] ?? 0));
        break;
    case 'clear':
        CartService::clear();
        break;
    default:
        Response::error('Unknown action');
}

Response::ok([
    'items'  => CartService::items(),
    'totals' => CartService::totals(),
]);
