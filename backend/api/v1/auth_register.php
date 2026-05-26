<?php
/**
 * POST /api/v1/?route=auth/register
 * Body: { name, email, password, role: buyer|seller, shop_name? }
 */
RateLimitMiddleware::enforce('auth', config('rate_limit.auth_per_minute', 8));

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$role = $body['role'] ?? 'buyer';

$res = AuthService::register($body, $role);
if (!$res['ok']) Response::error($res['message'], 400);

$user  = UserModel::findById((int) $res['user_id']);
$token = AuthService::issueToken($user);
unset($user['password']);

Response::created(['token' => $token, 'user' => $user]);
