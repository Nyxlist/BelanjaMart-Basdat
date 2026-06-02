<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('buyer');

$user        = current_user();
$displayCur  = $user['currency'] ?? 'IDR';

if (isset($_GET['add'])) {
    $r = CartService::add((int) $_GET['add'], 1);
    flash($r['ok'] ? 'ok' : 'err', $r['ok'] ? 'Added to cart' : ($r['message'] ?? 'Failed'));
    redirect($_SERVER['HTTP_REFERER'] ?? '/frontend/pages/buyer/home.php');
}
if (isset($_GET['remove'])) {
    CartService::remove((int) $_GET['remove']);
    redirect('/frontend/pages/buyer/cart.php');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) { flash('err', 'Session expired, please try again.'); redirect('/frontend/pages/buyer/cart.php'); }
    if (isset($_POST['update']) && !empty($_POST['qty'])) {
        CartService::update((array) $_POST['qty']);
        flash('ok', 'Cart updated');
    }
    redirect('/frontend/pages/buyer/cart.php');
}

$items  = CartService::items();
$totals = CartService::totals($displayCur);

layout('header', ['title' => 'Cart']);
?>
<?php component('flash'); ?>
<div class="steps">
    <div class="step active">Cart</div>
    <div class="step">Checkout</div>
    <div class="step">Tracking</div>
</div>

<h2 class="mb-2">Shopping Cart</h2>

<?php if (empty($items)): ?>
    <div class="card center" style="padding:50px; flex-direction:column;">
        <p class="text-muted">Your cart is empty.</p>
        <a class="btn btn-primary" href="<?= base_url('/frontend/pages/buyer/home.php') ?>">Start shopping</a>
    </div>
<?php else: ?>

<form method="POST">
    <?= csrf_field() ?>
    <div class="card" style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr>
                    <td>
                        <div class="fw-600"><?= e($it['product_name']) ?></div>
                        <div class="text-muted fs-13">Seller: <?= e($it['shop_name'] ?? $it['seller_name']) ?></div>
                    </td>
                    <td><?= Currency::display((float) $it['price'], $it['currency_code'], $displayCur) ?></td>
                    <td>
                        <input class="input" style="width:80px;" type="number"
                               name="qty[<?= (int) $it['product_id'] ?>]"
                               value="<?= (int) $it['qty'] ?>" min="0" max="<?= (int) $it['stock'] ?>">
                    </td>
                    <td><?= Currency::display((float) $it['subtotal'], $it['currency_code'], $displayCur) ?></td>
                    <td>
                        <a class="btn btn-ghost btn-sm" data-confirm="Remove this item?"
                           href="<?= base_url('/frontend/pages/buyer/cart.php?remove=' . (int) $it['product_id']) ?>">Remove</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="row mt-2">
        <button type="submit" name="update" class="btn btn-info">Update cart</button>
        <div class="spacer"></div>
        <a class="btn btn-outline" href="<?= base_url('/frontend/pages/buyer/home.php') ?>">← Continue shopping</a>
    </div>
</form>

<div class="card mt-3">
    <h3>Summary</h3>
    <div class="row" style="justify-content:space-between;">
        <span>Items</span><span><?= (int) $totals['count'] ?></span>
    </div>
    <div class="row" style="justify-content:space-between;">
        <span>Estimated subtotal</span>
        <span class="fw-bold" style="color:var(--color-primary); font-size:18px;">
            <?= Currency::format((float) $totals['subtotal'], $totals['currency']) ?>
        </span>
    </div>
    <div class="text-soft fs-13 mt-1">Tax &amp; shipping calculated at checkout based on your address.</div>
    <a class="btn btn-primary btn-block btn-lg mt-2" href="<?= base_url('/frontend/pages/buyer/checkout.php') ?>">Proceed to checkout →</a>
</div>
<?php endif; ?>

<?php layout('footer'); ?>
