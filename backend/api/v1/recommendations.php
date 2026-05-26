<?php
/**
 * GET /api/v1/?route=recommendations         - logged-in personalized
 * GET /api/v1/?route=recommendations&kind=trending
 * GET /api/v1/?route=recommendations&kind=similar&product_id=N
 */
$kind = $_GET['kind'] ?? 'top';

if ($kind === 'similar' && !empty($_GET['product_id'])) {
    Response::ok(RecommendationService::similar((int) $_GET['product_id']));
}
if ($kind === 'trending') {
    Response::ok(RecommendationService::trending());
}

$user = AuthService::userFromRequest();
if (!$user) {
    Response::ok(RecommendationService::trending());
}
Response::ok(RecommendationService::topPicks((int) $user['user_id']));
