<?php
/**
 * GET /api/v1/?route=auth/me  - returns current user from JWT.
 */
$user = AuthMiddleware::requireApi();
unset($user['password']);
Response::ok($user);
