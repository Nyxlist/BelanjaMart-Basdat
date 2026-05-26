<?php
/**
 * Chat endpoints.
 *
 *  GET  /api/v1/?route=chat&action=rooms
 *  GET  /api/v1/?route=chat&action=poll&chat_id=N&after_id=N   (long-poll-ish)
 *  POST /api/v1/?route=chat&action=send  body: chat_id, body, attachment
 *  POST /api/v1/?route=chat&action=open  body: seller_id, order_id?
 *  POST /api/v1/?route=chat&action=typing body: chat_id
 *  POST /api/v1/?route=chat&action=read   body: chat_id
 */
$user   = AuthMiddleware::requireApi();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($method === 'GET' && $action === 'rooms') {
    Response::ok(ChatModel::listForUser((int) $user['user_id']));
}

if ($method === 'GET' && $action === 'poll') {
    $chatId  = (int) ($_GET['chat_id']  ?? 0);
    $afterId = (int) ($_GET['after_id'] ?? 0);
    Response::ok(ChatService::pollMessages($chatId, (int) $user['user_id'], $afterId));
}

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

if ($method === 'POST' && $action === 'send') {
    $r = ChatService::send(
        (int) ($body['chat_id'] ?? 0),
        (int) $user['user_id'],
        (string) ($body['body'] ?? ''),
        $_FILES['attachment'] ?? null
    );
    if (!$r['ok']) Response::error($r['message']);
    Response::ok($r);
}

if ($method === 'POST' && $action === 'open') {
    $sellerId = (int) ($body['seller_id'] ?? 0);
    $orderId  = !empty($body['order_id']) ? (int) $body['order_id'] : null;
    if ($user['role'] !== 'buyer') Response::forbidden('Only buyers open chats');
    Response::ok(ChatService::openRoom((int) $user['user_id'], $sellerId, $orderId));
}

if ($method === 'POST' && $action === 'typing') {
    ChatModel::setTyping((int) ($body['chat_id'] ?? 0), (int) $user['user_id']);
    Response::ok(['ok' => true]);
}

if ($method === 'POST' && $action === 'read') {
    ChatService::markRead((int) ($body['chat_id'] ?? 0), (int) $user['user_id']);
    Response::ok(['ok' => true]);
}

Response::error('Unknown chat action');
