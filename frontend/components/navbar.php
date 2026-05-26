<?php
/** Smart navbar.  Different actions per role + dark/light toggle. */
$role = $user['role'] ?? null;
?>
<header class="topbar">
    <a href="<?= base_url('/') ?>" class="brand">Belanja<span>Mart</span></a>

    <?php if ($role !== 'admin'): ?>
    <form class="search" action="<?= base_url('/frontend/pages/buyer/home.php') ?>" method="GET">
        <input type="text" name="q" placeholder="Cari produk, brand, atau seller..." value="<?= e($_GET['q'] ?? '') ?>">
        <button type="submit">🔍</button>
    </form>
    <?php endif; ?>

    <div class="spacer"></div>

    <div class="topbar-actions">
        <button class="icon-btn" data-theme-toggle title="Toggle theme">
            <span class="icon">🌙</span>
        </button>

        <?php if (!$user): ?>
            <a class="pill" href="<?= base_url('/auth/role.php') ?>">Sign in</a>
        <?php else: ?>
            <?php if ($role === 'buyer'): ?>
                <a class="pill" href="<?= base_url('/frontend/pages/buyer/wishlist.php') ?>" title="Wishlist">
                    ❤️ <span class="label">Wishlist</span>
                    <?php if (!empty($wishCount)): ?><span class="badge-count"><?= (int) $wishCount ?></span><?php endif; ?>
                </a>
                <a class="pill" href="<?= base_url('/frontend/pages/buyer/cart.php') ?>" title="Cart">
                    🛒 <span class="label">Cart</span>
                    <?php if (!empty($cartCount)): ?><span class="badge-count"><?= (int) $cartCount ?></span><?php endif; ?>
                </a>
                <a class="pill" href="<?= base_url('/frontend/pages/buyer/orders.php') ?>" title="Orders">
                    📦 <span class="label">Orders</span>
                </a>
            <?php elseif ($role === 'seller'): ?>
                <a class="pill" href="<?= base_url('/frontend/pages/seller/dashboard.php') ?>">📊 <span class="label">Dashboard</span></a>
                <a class="pill" href="<?= base_url('/frontend/pages/seller/products.php') ?>">📦 <span class="label">Products</span></a>
                <a class="pill" href="<?= base_url('/frontend/pages/seller/orders.php') ?>">📋 <span class="label">Orders</span></a>
            <?php elseif ($role === 'admin'): ?>
                <a class="pill" href="<?= base_url('/frontend/pages/admin/dashboard.php') ?>">🛡 Admin</a>
            <?php endif; ?>

            <a class="pill" href="<?= base_url('/frontend/pages/shared/chat.php') ?>" title="Messages">
                💬
            </a>
            <a class="pill" href="<?= base_url('/frontend/pages/shared/notifications.php') ?>" title="Notifications">
                🔔
                <?php if (!empty($notifCount)): ?><span class="badge-count"><?= (int) $notifCount ?></span><?php endif; ?>
            </a>

            <a class="pill" href="<?= base_url('/auth/logout.php') ?>" title="Logout">
                👋 <span class="label"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
            </a>
        <?php endif; ?>
    </div>
</header>
