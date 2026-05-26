<?php
/**
 * GET  /api/v1/?route=orders                 (buyer or seller's own list)
 * GET  /api/v1/?route=orders&id=N            (single order)
 * POST /api/v1/?route=orders&action=checkout body: address_id
 * POST /api/v1/?route=orders&action=pay      body: order_id
 * POST /api/v1/?route=orders&action=ship     body: order_id, tracking_number, courier
 * POST /api/v1/?route=orders&action=delivered body: order_id
 * POST /api/v1/?route=orders&action=cancel   body: order_id, reason_id, note
 */
$user   = AuthMiddleware::requireApi();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body   = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$action = $_GET['action'] ?? $body['action'] ?? '';

if ($method === 'GET' && empty($_GET['id'])) {
    if ($user['role'] === 'seller') {
        Response::ok(OrderModel::forSeller((int) $user['user_id']));
    }
    Response::ok(OrderModel::forBuyer((int) $user['user_id']));
}

if ($method === 'GET' && !empty($_GET['id'])) {
    $order = OrderModel::find((int) $_GET['id']);
    if (!$order) Response::notFound();
    $order['items']    = OrderModel::items((int) $order['order_id']);
    $order['tracking'] = OrderModel::tracking((int) $order['order_id']);
    Response::ok($order);
}

switch ($action) {
    case 'checkout':
        if ($user['role'] !== 'buyer') Response::forbidden();
        $r = OrderService::checkout(
            (int) $user['user_id'],
            (int) ($body['address_id'] ?? 0),
            $body['payment_method'] ?? 'simulation'
        );
        break;
    case 'pay':
        $r = OrderService::markPaid((int) ($body['order_id'] ?? 0), (int) $user['user_id']);
        break;
    case 'ship':
        if ($user['role'] !== 'seller') Response::forbidden();
        $r = OrderService::ship(
            (int) ($body['order_id'] ?? 0),
            (int) $user['user_id'],
            (string) ($body['tracking_number'] ?? ''),
            (string) ($body['courier'] ?? 'Local')
        );
        break;
    case 'delivered':
        $r = OrderService::confirmDelivered(
            (int) ($body['order_id'] ?? 0),
            (int) $user['user_id']
        );
        break;
    case 'cancel':
        $r = OrderService::cancel(
            (int) ($body['order_id'] ?? 0),
            (int) $user['user_id'],
            $user['role'],
            $body
        );
        break;
    default:
        Response::error('Unknown action');
}

if (!$r['ok']) Response::error($r['message']);
Response::ok($r);
