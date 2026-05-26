<?php
/**
 * Front controller / landing page.
 * Sends visitors to the right starting page based on their role.
 */
require_once __DIR__ . '/backend/config/bootstrap.php';

if (!is_logged_in()) {
    redirect('/frontend/pages/shared/role.php');
}

$role = $_SESSION['user']['role'] ?? 'buyer';
match ($role) {
    'seller' => redirect('/frontend/pages/seller/dashboard.php'),
    'admin'  => redirect('/frontend/pages/admin/dashboard.php'),
    default  => redirect('/frontend/pages/buyer/home.php'),
};
