<?php
require_once __DIR__ . '/../backend/config/bootstrap.php';
$_GET['id'] = $_GET['id'] ?? $_GET['product_id'] ?? null;
require __DIR__ . '/../frontend/pages/shared/product_detail.php';
