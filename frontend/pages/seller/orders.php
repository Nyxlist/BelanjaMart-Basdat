<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('seller');

$user    = current_user();
$reasons = CancellationModel::reasonsFor('seller');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) { flash('err', 'Session expired, please try again.'); redirect('/frontend/pages/seller/orders.php'); }

    $action  = $_POST['action'] ?? '';
    $orderId = (int) ($_POST['order_id'] ?? 0);

    if ($action === 'ship') {
        $r = OrderService::ship($orderId, (int) $user['user_id'],
            (string) ($_POST['tracking_number'] ?? ''),
            (string) ($_POST['courier'] ?? ''));
        flash($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'Order marked as shipped.' : $r['message']);
    } elseif ($action === 'cancel') {
        $r = OrderService::cancel($orderId, (int) $user['user_id'], 'seller', $_POST);
        flash($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'Order cancelled.' : $r['message']);
    }
    redirect('/frontend/pages/seller/orders.php');
}

$orders = OrderModel::forSeller((int) $user['user_id']);

layout('header', ['title' => 'Seller Orders']);
?>
<?php component('flash'); ?>
<div class="layout">
    <?php component('sidebar_seller'); ?>
    <div>
        <h2>Incoming orders</h2>
        <?php if (empty($orders)): ?>
            <div class="card center" style="padding:50px; flex-direction:column;">
                <p class="text-muted">No incoming orders yet.</p>
            </div>
        <?php else: ?>
            <div class="card mt-2" style="overflow-x:auto;">
                <table class="table">
                    <thead><tr><th>#</th><th>Buyer</th><th>Products</th><th>Subtotal</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td>#<?= (int) $o['order_id'] ?><br><small class="text-muted"><?= date('d M Y', strtotime($o['order_date'])) ?></small></td>
                            <td><?= e($o['buyer_name']) ?></td>
                            <td><?= e($o['product_names']) ?></td>
                            <td><?= Currency::format((float) $o['subtotal'], $o['currency_code']) ?></td>
                            <td><span class="status status-<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
                            <td>
                                <?php if (in_array($o['status'], ['paid', 'pending', 'processing'], true)): ?>
                                    <button class="btn btn-info btn-sm" data-modal-open="ship-<?= (int) $o['order_id'] ?>">Ship</button>
                                    <button class="btn btn-outline btn-sm" data-modal-open="cancel-<?= (int) $o['order_id'] ?>">Cancel</button>
                                <?php elseif ($o['status'] === 'shipped'): ?>
                                    <span class="text-muted fs-13">Tracking: <?= e($o['tracking_number']) ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($orders as $o): ?>
                <?php if (in_array($o['status'], ['paid', 'pending', 'processing'], true)):
                    ob_start(); ?>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="ship">
                        <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                        <div class="form-row"><label>Courier</label>
                            <select name="courier"><option>JNE</option><option>SiCepat</option><option>J&amp;T</option><option>Pos</option><option>DHL</option></select>
                        </div>
                        <div class="form-row"><label>Tracking number</label>
                            <input type="text" name="tracking_number" required></div>
                        <button class="btn btn-info btn-block">Mark as shipped</button>
                    </form>
                    <?php $body = ob_get_clean();
                    component('modal', ['id' => "ship-{$o['order_id']}", 'title' => "Ship order #{$o['order_id']}", 'body' => $body]);

                    ob_start(); ?>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
                        <div class="form-row"><label>Reason</label>
                            <select name="reason_id" required>
                                <option value="">-- select reason --</option>
                                <?php foreach ($reasons as $r): ?>
                                    <option value="<?= (int) $r['reason_id'] ?>"><?= e($r['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row"><label>Explanation to buyer</label>
                            <textarea name="note" placeholder="Optional explanation..."></textarea></div>
                        <button class="btn btn-danger btn-block">Submit cancellation</button>
                    </form>
                    <?php $body = ob_get_clean();
                    component('modal', ['id' => "cancel-{$o['order_id']}", 'title' => "Cancel order #{$o['order_id']}", 'body' => $body]);
                endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php layout('footer'); ?>
