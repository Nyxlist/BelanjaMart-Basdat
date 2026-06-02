<?php
/** Smart navbar.  Different actions per role + dark/light toggle + language selector. */
$role = $user['role'] ?? null;
$currentLang = Lang::locale();
$langFlags = ['en' => 'EN', 'id' => 'ID', 'ja' => 'JA', 'zh' => 'ZH'];
?>
<header class="topbar">
    <a href="<?= base_url('/') ?>" class="brand">Belanja<span>Mart</span></a>

    <?php if ($role !== 'admin'): ?>
    <form class="search" action="<?= base_url('/frontend/pages/buyer/home.php') ?>" method="GET">
        <input type="text" name="q" placeholder="<?= e(__('Search products, brands, or sellers...')) ?>" value="<?= e($_GET['q'] ?? '') ?>">
        <button type="submit">Search</button>
    </form>
    <?php endif; ?>

    <div class="spacer"></div>

    <div class="topbar-actions">
        <!-- Language selector -->
        <div class="lang-switcher" style="position:relative;">
            <button class="icon-btn" id="langToggle" title="<?= e(__('Language')) ?>">
                <span><?= $langFlags[$currentLang] ?? 'Lang' ?></span>
            </button>
            <div class="lang-dropdown" id="langDropdown">
                <?php foreach (Lang::available() as $code => $label): ?>
                    <a href="<?= base_url('/frontend/pages/shared/set_lang.php?lang=' . $code) ?>"
                       class="<?= $code === $currentLang ? 'active' : '' ?>">
                        <?= $langFlags[$code] ?? $code ?> <?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <button class="icon-btn" data-theme-toggle title="<?= e(__('Toggle theme')) ?>">
            <span class="icon">Dark</span>
        </button>

        <?php if (!$user): ?>
            <a class="pill" href="<?= base_url('/auth/role.php') ?>"><?= e(__('Sign in')) ?></a>
        <?php else: ?>
            <?php if ($role === 'buyer'): ?>
                <a class="pill" href="<?= base_url('/frontend/pages/buyer/wishlist.php') ?>" title="<?= e(__('Wishlist')) ?>">
                    <span class="label"><?= e(__('Wishlist')) ?></span>
                    <?php if (!empty($wishCount)): ?><span class="badge-count"><?= (int) $wishCount ?></span><?php endif; ?>
                </a>
                <a class="pill" href="<?= base_url('/frontend/pages/buyer/cart.php') ?>" title="<?= e(__('Cart')) ?>">
                    <span class="label"><?= e(__('Cart')) ?></span>
                    <?php if (!empty($cartCount)): ?><span class="badge-count"><?= (int) $cartCount ?></span><?php endif; ?>
                </a>
                <a class="pill" href="<?= base_url('/frontend/pages/buyer/orders.php') ?>" title="<?= e(__('Orders')) ?>">
                    <span class="label"><?= e(__('Orders')) ?></span>
                </a>
            <?php elseif ($role === 'seller'): ?>
                <a class="pill" href="<?= base_url('/frontend/pages/seller/dashboard.php') ?>"><span class="label"><?= e(__('Dashboard')) ?></span></a>
                <a class="pill" href="<?= base_url('/frontend/pages/seller/products.php') ?>"><span class="label"><?= e(__('Products')) ?></span></a>
                <a class="pill" href="<?= base_url('/frontend/pages/seller/orders.php') ?>"><span class="label"><?= e(__('Orders')) ?></span></a>
            <?php elseif ($role === 'admin'): ?>
                <a class="pill" href="<?= base_url('/frontend/pages/admin/dashboard.php') ?>">Admin</a>
            <?php endif; ?>

            <a class="pill" href="<?= base_url('/frontend/pages/shared/chat.php') ?>" title="<?= e(__('Messages')) ?>">
                <?= e(__('Messages')) ?>
            </a>
            <a class="pill" href="<?= base_url('/frontend/pages/shared/notifications.php') ?>" title="<?= e(__('Notifications')) ?>">
                <?= e(__('Notifications')) ?>
                <?php if (!empty($notifCount)): ?><span class="badge-count"><?= (int) $notifCount ?></span><?php endif; ?>
            </a>

            <a class="pill" href="<?= base_url('/auth/logout.php') ?>" title="<?= e(__('Logout')) ?>">
                <span class="label"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
            </a>
        <?php endif; ?>
    </div>
</header>
