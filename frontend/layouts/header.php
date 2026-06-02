<?php
/**
 * Master HTML <head> + opening <body>.
 * Pages should:
 *   layout('header', ['title' => 'My Page']);
 *   ... content ...
 *   layout('footer');
 */
$user        = current_user();
$cartCount   = CartService::count();
$wishCount   = $user ? WishlistModel::count((int) $user['user_id']) : 0;
$notifCount  = $user ? NotificationModel::unreadCount((int) $user['user_id']) : 0;
$pageTitle   = $title ?? 'BelanjaMart';
?><!DOCTYPE html>
<html lang="<?= e(Lang::locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · BelanjaMart</title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <script defer src="<?= asset('js/app.js') ?>"></script>
</head>
<body>

<?php component('navbar', [
    'user'       => $user,
    'cartCount'  => $cartCount,
    'wishCount'  => $wishCount,
    'notifCount' => $notifCount,
]); ?>

<main class="container" style="padding: 24px 16px;">
