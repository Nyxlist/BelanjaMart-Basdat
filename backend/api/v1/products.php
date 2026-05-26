<?php
/**
 * GET /api/v1/?route=products            - list with filters
 * GET /api/v1/?route=products/{id}       - single product
 */

if (!empty($_GET['id'])) {
    $product = ProductModel::find((int) $_GET['id']);
    if (!$product) Response::notFound();
    $product['reviews'] = ReviewModel::forProduct((int) $product['product_id']);
    $product['similar'] = RecommendationService::similar((int) $product['product_id']);
    Response::ok($product);
}

$filters = [
    'category_id' => $_GET['category_id'] ?? null,
    'search'      => $_GET['search']      ?? null,
    'min_rating'  => $_GET['min_rating']  ?? null,
    'sort'        => $_GET['sort']        ?? null,
    'limit'       => (int) ($_GET['limit']  ?? 24),
    'offset'      => (int) ($_GET['offset'] ?? 0),
    'in_stock'    => true,
];
Response::ok(ProductModel::search($filters));
