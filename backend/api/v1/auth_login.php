<?php
/**
 * POST /api/v1/?route=auth/login
 * Body: { email, password }
 * Returns: { token, user }
 */
RateLimitMiddleware::enforce('auth', config('rate_limit.auth_per_minute', 8));

$body = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$v = new Validator($body, [
    'email'    => ['required', 'email'],
    'password' => ['required', 'min:6'],
]);
if ($v->fails()) Response::validation($v->errors());

$user = AuthService::attempt($body['email'], $body['password']);
if (!$user) Response::unauthorized('Invalid credentials');

$token = AuthService::issueToken($user);
unset($user['password']);
Response::ok(['token' => $token, 'user' => $user]);
