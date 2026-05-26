<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('seller');

$user    = current_user();
$stats   = AnalyticsService::sellerDashboard((int) $user['user_id']);
$badges  = UserModel::badges((int) $user['user_id']);
$profile = UserModel::sellerProfile((int) $user['user_id']);

layout('header', ['title' => 'Seller Dashboard']);
?>
<?php component('flash'); ?>
<div class="layout">
    <?php component('sidebar_seller'); ?>
    <div>
        <!-- Greeting card -->
        <div class="card" style="background: linear-gradient(120deg, var(--color-info), #5dade2); color:#fff;">
            <h2><?= e($profile['shop_name'] ?? $user['name']) ?> 🏪</h2>
            <p style="opacity:.95;">Welcome back! Here's your shop at a glance.</p>
            <?php component('seller_badge', ['badges' => $badges]); ?>
        </div>

        <!-- Stats -->
        <div class="grid mt-3" style="grid-template-columns: repeat(auto-fit, minmax(180px,1fr));">
            <div class="card"><div class="text-muted fs-13">Products</div><div class="fw-bold" style="font-size:24px;"><?= (int) $stats['totalProducts'] ?></div></div>
            <div class="card"><div class="text-muted fs-13">Stock units</div><div class="fw-bold" style="font-size:24px;"><?= (int) $stats['totalStock'] ?></div></div>
            <div class="card"><div class="text-muted fs-13">Open orders</div><div class="fw-bold" style="font-size:24px; color:var(--color-warning);"><?= (int) $stats['newOrders'] ?></div></div>
            <div class="card"><div class="text-muted fs-13">Revenue (30d)</div><div class="fw-bold" style="font-size:20px; color:var(--color-success);">
                <?= Currency::format((float) $stats['revenue30'], $user['currency'] ?? 'IDR') ?>
            </div></div>
        </div>

        <!-- Reputation -->
        <div class="card mt-3">
            <h3>📈 Reputation</h3>
            <div class="row" style="gap:24px; flex-wrap:wrap;">
                <div><div class="text-muted fs-13">Avg rating</div>
                    <div class="fw-bold" style="font-size:22px;">⭐ <?= number_format($stats['rep']['avg_rating'] ?? 0, 2) ?></div></div>
                <div><div class="text-muted fs-13">Reviews</div>
                    <div class="fw-bold" style="font-size:22px;"><?= (int) ($stats['rep']['total_reviews'] ?? 0) ?></div></div>
                <div><div class="text-muted fs-13">Completed sales</div>
                    <div class="fw-bold" style="font-size:22px;"><?= (int) ($stats['rep']['total_sales'] ?? 0) ?></div></div>
                <div><div class="text-muted fs-13">Cancellation rate</div>
                    <div class="fw-bold" style="font-size:22px; color:var(--color-danger);"><?= number_format($stats['rep']['cancellation_rate'] ?? 0, 2) ?>%</div></div>
                <div><div class="text-muted fs-13">Avg response</div>
                    <div class="fw-bold" style="font-size:22px;"><?= (int) ($stats['rep']['response_time'] ?? 0) ?>m</div></div>
            </div>
        </div>

        <!-- Top products -->
        <div class="card mt-3">
            <h3>🔥 Top products</h3>
            <?php if (empty($stats['topProducts'])): ?>
                <p class="text-muted">No sales yet - add products to start selling.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Product</th><th>Sold</th><th>Rating</th></tr></thead>
                    <tbody>
                    <?php foreach ($stats['topProducts'] as $p): ?>
                        <tr>
                            <td><?= e($p['product_name']) ?></td>
                            <td><?= (int) $p['total_sold'] ?></td>
                            <td>⭐ <?= number_format((float) $p['average_rating'], 1) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Mini chart -->
        <div class="card mt-3">
            <h3>📊 Revenue last 14 days</h3>
            <?php
            $chart = $stats['byDay'];
            $max = 1;
            foreach ($chart as $c) $max = max($max, (float) $c['revenue']);
            ?>
            <?php if (empty($chart)): ?>
                <p class="text-muted">No revenue yet.</p>
            <?php else: ?>
                <div style="display:flex; align-items:flex-end; gap:6px; height:140px;">
                    <?php foreach ($chart as $c): $h = round((float) $c['revenue'] / $max * 100); ?>
                        <div title="<?= e($c['d']) ?>: <?= Currency::format((float) $c['revenue'], $user['currency'] ?? 'IDR') ?>"
                             style="flex:1; min-width:18px; background: var(--color-primary); border-radius: 4px 4px 0 0; height: <?= max(5, $h) ?>%;"></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php layout('footer'); ?>
