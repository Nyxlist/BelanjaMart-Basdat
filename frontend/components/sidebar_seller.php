<?php
$current = basename($_SERVER['PHP_SELF']);
$items = [
    ['dashboard.php',   'Dashboard'],
    ['products.php',    'Products'],
    ['orders.php',      'Orders'],
    ['pricing.php',     'Smart Pricing'],
    ['profile.php',     'Shop Profile'],
];
?>
<aside class="sidebar card card-tight">
    <h3 class="fs-13 text-muted" style="text-transform:uppercase; letter-spacing:.05em; margin-bottom:8px;">Seller</h3>
    <?php foreach ($items as [$file, $label]): ?>
        <a href="<?= base_url('/frontend/pages/seller/' . $file) ?>"
           class="<?= $current === $file ? 'active' : '' ?>">
            <?= $label ?>
        </a>
    <?php endforeach; ?>
    <a href="<?= base_url('/frontend/pages/shared/chat.php') ?>">Messages</a>
</aside>
