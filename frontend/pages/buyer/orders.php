<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('buyer');

$user = current_user();
$reasons = CancellationModel::reasonsFor('buyer');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) { flash('err', 'Session expired, please try again.'); redirect('/frontend/pages/buyer/orders.php'); }
    $action  = $_POST['action'] ?? '';
    $orderId = (int) ($_POST['order_id'] ?? 0);

    if ($action === 'pay') {
        $r = OrderService::markPaid($orderId, (int) $user['user_id']);
        flash($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'Payment confirmed!' : $r['message']);
    } elseif ($action === 'received') {
        $r = OrderService::confirmDelivered($orderId, (int) $user['user_id']);
        flash($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'Marked as delivered.' : $r['message']);
    } elseif ($action === 'cancel') {
        $r = OrderService::cancel($orderId, (int) $user['user_id'], 'buyer', $_POST);
        flash($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'Order cancelled.' : $r['message']);
    }
    redirect('/frontend/pages/buyer/orders.php');
}

$orders = OrderModel::forBuyer((int) $user['user_id']);

layout('header', ['title' => 'My orders']);
?>
<?php component('flash'); ?>
<h2 class="mb-2">My Orders</h2>

<?php if (empty($orders)): ?>
    <div class="card center" style="padding:50px; flex-direction:column;">
        <p class="text-muted">No orders yet.</p>
        <a class="btn btn-primary" href="<?= base_url('/frontend/pages/buyer/home.php') ?>">Start shopping</a>
    </div>
<?php else: foreach ($orders as $o):
    $items    = OrderModel::items((int) $o['order_id']);
    $tracking = OrderModel::tracking((int) $o['order_id']);
?>
<div class="card mb-2">
    <div class="row" style="justify-content:space-between; flex-wrap:wrap;">
        <div>
            <div class="fw-bold">Order #<?= (int) $o['order_id'] ?></div>
            <div class="text-muted fs-13"><?= date('d M Y, H:i', strtotime($o['order_date'])) ?></div>
        </div>
        <span class="status status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span>
    </div>
    <div class="divider"></div>
    <?php foreach ($items as $it): ?>
        <div class="row" style="justify-content:space-between; padding:6px 0;">
            <div>
                <div class="fw-600"><?= e($it['product_name']) ?></div>
                <div class="text-muted fs-13">Seller: <?= e($it['shop_name'] ?? $it['seller_name']) ?> · <?= (int) $it['quantity'] ?> × <?= Currency::format((float) $it['price'], $o['currency_code']) ?></div>
            </div>
            <div class="fw-bold"><?= Currency::format((float) $it['subtotal'], $o['currency_code']) ?></div>
        </div>
    <?php endforeach; ?>
    <div class="divider"></div>
    <div class="row" style="justify-content:space-between; align-items:center;">
        <div>
            <div class="fs-13 text-muted">Total</div>
            <div class="fw-bold" style="color:var(--color-primary); font-size:18px;"><?= Currency::format((float) $o['total_amount'], $o['currency_code']) ?></div>
        </div>
        <div class="row" style="gap:6px;">
            <?php if ($o['status'] === 'pending'): ?>
                <form method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                    <button class="btn btn-success btn-sm">Pay (simulate)</button>
                </form>
                <button class="btn btn-outline btn-sm" data-modal-open="cancelModal-<?= (int) $o['order_id'] ?>">Cancel</button>
            <?php endif; ?>
            <?php if ($o['status'] === 'shipped'): ?>
                <form method="POST" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="received">
                    <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                    <button class="btn btn-success btn-sm">Received</button>
                </form>
            <?php endif; ?>
            <?php if ($o['status'] === 'delivered'): ?>
                <?php foreach ($items as $it): ?>
                    <?php if (empty($it['has_review'])): ?>
                        <a class="btn btn-info btn-sm"
                           href="<?= base_url('/frontend/pages/shared/review.php?order_item_id=' . (int) $it['order_item_id']) ?>">
                            Review
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($tracking)): ?>
                <button class="btn btn-ghost btn-sm" data-modal-open="trackModal-<?= (int) $o['order_id'] ?>">Track</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Cancellation modal -->
<?php if ($o['status'] === 'pending'):
    ob_start(); ?>
    <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
        <div class="form-row">
            <label>Reason</label>
            <select name="reason_id" required>
                <option value="">-- Select reason --</option>
                <?php foreach ($reasons as $r): ?>
                    <option value="<?= (int) $r['reason_id'] ?>" <?= !empty($r['requires_note']) ? 'data-note="1"' : '' ?>>
                        <?= e($r['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <label>Note (optional)</label>
            <textarea name="note" placeholder="Add details to help the seller understand..."></textarea>
        </div>
        <button class="btn btn-danger btn-block">Submit cancellation</button>
    </form>
    <?php $body = ob_get_clean();
    component('modal', ['id' => "cancelModal-{$o['order_id']}", 'title' => "Cancel order #{$o['order_id']}", 'body' => $body]);
endif; ?>

<!-- Tracking modal -->
<?php if (!empty($tracking)):
    ob_start(); ?>
    <ul style="list-style:none; padding-left:0;">
        <?php foreach ($tracking as $t): ?>
            <li style="border-left: 3px solid var(--color-primary); padding: 4px 0 12px 12px; margin-left:6px;">
                <div class="fw-600"><?= e($t['event_label']) ?></div>
                <div class="text-muted fs-13"><?= e($t['location'] ?? '-') ?> · <?= date('d M Y, H:i', strtotime($t['happened_at'])) ?></div>
                <?php if ($t['note']): ?>
                    <div class="fs-13"><?= e($t['note']) ?></div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php $body = ob_get_clean();
    component('modal', ['id' => "trackModal-{$o['order_id']}", 'title' => "Tracking #{$o['order_id']}", 'body' => $body]);
endif; ?>

<?php endforeach; endif; ?>
<?php layout('footer'); ?>
