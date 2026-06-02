<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('admin');

$counts = [
    'users'    => (int) Database::scalar("SELECT COUNT(*) FROM users"),
    'sellers'  => (int) Database::scalar("SELECT COUNT(*) FROM users WHERE role='seller'"),
    'products' => (int) Database::scalar("SELECT COUNT(*) FROM products"),
    'orders'   => (int) Database::scalar("SELECT COUNT(*) FROM orders"),
    'fraud'    => (int) Database::scalar("SELECT COUNT(*) FROM fraud_flags WHERE resolved = 0"),
];

$recentOrders = Database::all("
    SELECT o.*, u.name AS buyer_name
    FROM orders o JOIN users u ON u.user_id = o.user_id
    ORDER BY o.order_date DESC LIMIT 10
");
$frauds = Database::all("
    SELECT f.*, u.name AS user_name FROM fraud_flags f
    LEFT JOIN users u ON u.user_id = f.user_id
    WHERE f.resolved = 0 ORDER BY f.created_at DESC LIMIT 10
");
$pendingSellers = Database::all("
    SELECT u.user_id, u.name, u.email, sp.shop_name
    FROM users u JOIN seller_profiles sp ON sp.user_id = u.user_id
    WHERE sp.is_verified = 0 LIMIT 10
");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) { flash('err','Session expired, please try again.'); redirect('/frontend/pages/admin/dashboard.php'); }
    if (($_POST['action'] ?? '') === 'verify_seller') {
        $sid = (int) $_POST['user_id'];
        Database::update('seller_profiles', ['is_verified' => 1], 'user_id = ?', [$sid]);
        Database::update('users', ['is_verified' => 1], 'user_id = ?', [$sid]);
        ReputationService::awardBadges($sid);
        flash('ok', 'Seller verified.');
    }
    if (($_POST['action'] ?? '') === 'resolve_fraud') {
        Database::update('fraud_flags', ['resolved' => 1], 'flag_id = ?', [(int) $_POST['flag_id']]);
        flash('ok', 'Flag resolved.');
    }
    redirect('/frontend/pages/admin/dashboard.php');
}

layout('header', ['title' => 'Admin']);
?>
<?php component('flash'); ?>
<h2 class="mb-2">Admin Dashboard</h2>

<div class="grid" style="grid-template-columns: repeat(auto-fit,minmax(150px,1fr));">
    <div class="card"><div class="text-muted fs-13">Users</div><div class="fw-bold" style="font-size:22px;"><?= $counts['users'] ?></div></div>
    <div class="card"><div class="text-muted fs-13">Sellers</div><div class="fw-bold" style="font-size:22px;"><?= $counts['sellers'] ?></div></div>
    <div class="card"><div class="text-muted fs-13">Products</div><div class="fw-bold" style="font-size:22px;"><?= $counts['products'] ?></div></div>
    <div class="card"><div class="text-muted fs-13">Orders</div><div class="fw-bold" style="font-size:22px;"><?= $counts['orders'] ?></div></div>
    <div class="card"><div class="text-muted fs-13">Flagged orders</div><div class="fw-bold text-danger" style="font-size:22px;"><?= $counts['fraud'] ?></div></div>
</div>

<div class="grid mt-3" style="grid-template-columns: 1fr 1fr; gap:16px;">
    <div class="card">
        <h3>Pending seller verifications</h3>
        <?php if (empty($pendingSellers)): ?>
            <p class="text-muted">All sellers are verified.</p>
        <?php else: foreach ($pendingSellers as $s): ?>
            <div class="row" style="justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--border);">
                <div>
                    <div class="fw-600"><?= e($s['shop_name']) ?> · <?= e($s['name']) ?></div>
                    <div class="text-muted fs-13"><?= e($s['email']) ?></div>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="verify_seller">
                    <input type="hidden" name="user_id" value="<?= (int) $s['user_id'] ?>">
                    <button class="btn btn-success btn-sm">Verify</button>
                </form>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="card">
        <h3>Fraud flags</h3>
        <?php if (empty($frauds)): ?>
            <p class="text-muted">No open flags.</p>
        <?php else: foreach ($frauds as $f): ?>
            <div class="row" style="justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--border);">
                <div>
                    <div class="fw-600"><?= e($f['user_name'] ?? '—') ?> · score <?= (int) $f['score'] ?></div>
                    <div class="text-muted fs-13"><?= e($f['reason']) ?></div>
                </div>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="resolve_fraud">
                    <input type="hidden" name="flag_id" value="<?= (int) $f['flag_id'] ?>">
                    <button class="btn btn-outline btn-sm">Resolve</button>
                </form>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div class="card mt-3">
    <h3>Recent orders</h3>
    <table class="table">
        <thead><tr><th>#</th><th>Buyer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($recentOrders as $o): ?>
            <tr>
                <td>#<?= (int) $o['order_id'] ?></td>
                <td><?= e($o['buyer_name']) ?></td>
                <td><?= Currency::format((float) $o['total_amount'], $o['currency_code']) ?></td>
                <td><span class="status status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                <td><?= date('d M Y, H:i', strtotime($o['order_date'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php layout('footer'); ?>
