<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('buyer');

$user      = current_user();
$items     = CartService::items();
if (empty($items)) redirect('/frontend/pages/buyer/cart.php');

$addresses = AddressModel::listFor((int) $user['user_id']);
$default   = $addresses[0] ?? null;
$err       = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) { flash('err', 'Invalid form session'); redirect('/frontend/pages/buyer/checkout.php'); }

    if (isset($_POST['action']) && $_POST['action'] === 'add_address') {
        AddressModel::create((int) $user['user_id'], $_POST + [
            'is_default' => 1,
            'country_code' => $_POST['country_code'] ?? ($user['country'] ?? 'ID'),
        ]);
        flash('ok', 'Address saved.');
        redirect('/frontend/pages/buyer/checkout.php');
    }

    if (isset($_POST['action']) && $_POST['action'] === 'place_order') {
        $r = OrderService::checkout((int) $user['user_id'], (int) ($_POST['address_id'] ?? 0));
        if ($r['ok']) {
            flash('ok', 'Order #' . $r['order_id'] . ' placed!');
            redirect('/frontend/pages/buyer/orders.php');
        }
        $err = $r['message'];
    }
}

// Live total preview
$displayCur  = $default['country_code'] ?? null
    ? Database::scalar("SELECT currency_code FROM countries WHERE country_code = ?", [$default['country_code']])
    : ($user['currency'] ?? 'IDR');
$displayCur  = $displayCur ?: 'IDR';
$country     = $default ? Database::one("SELECT * FROM countries WHERE country_code = ?", [$default['country_code']]) : null;
$subtotal    = 0;
foreach ($items as $it) {
    $subtotal += Currency::convert((float) $it['price'], $it['currency_code'], $displayCur) * $it['qty'];
}
$shipping    = (float) ($country['base_shipping'] ?? 0);
$tax         = $subtotal * (float) ($country['tax_rate'] ?? 0);
$total       = $subtotal + $shipping + $tax;

layout('header', ['title' => 'Checkout']);
?>
<?php component('flash'); ?>
<div class="steps">
    <div class="step">🛒 Cart</div>
    <div class="step active">✅ Checkout</div>
    <div class="step">📦 Tracking</div>
</div>

<?php if ($err): ?>
    <div class="toast toast-danger" style="position:relative; margin-bottom:12px;">⚠️ <?= e($err) ?></div>
<?php endif; ?>

<div class="grid" style="grid-template-columns: 2fr 1fr; gap:20px;">
    <div>
        <!-- Shipping address -->
        <div class="card">
            <h3>📍 Shipping address</h3>
            <?php if (empty($addresses)): ?>
                <p class="text-muted">No address yet - add one to continue.</p>
            <?php else: ?>
                <form method="POST" id="placeOrderForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="place_order">
                    <?php foreach ($addresses as $a): ?>
                        <label class="card card-tight" style="display:flex; gap:10px; cursor:pointer; margin-bottom:8px;">
                            <input type="radio" name="address_id" value="<?= (int) $a['address_id'] ?>"
                                   <?= $a['is_default'] ? 'checked' : '' ?> required>
                            <div>
                                <div class="fw-600"><?= e($a['recipient']) ?> · <?= e($a['phone']) ?></div>
                                <div class="text-muted fs-13"><?= e($a['line1']) ?>, <?= e($a['city']) ?>,
                                    <?= e($a['country_name']) ?> · <?= e($a['postal_code']) ?></div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </form>
            <?php endif; ?>
        </div>

        <!-- Add new address -->
        <div class="card mt-2">
            <h3>➕ Add new address</h3>
            <form method="POST" class="grid" style="grid-template-columns: 1fr 1fr; gap:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_address">
                <div class="form-row" style="grid-column: span 2;">
                    <label>Recipient</label>
                    <input type="text" name="recipient" required value="<?= e($user['name']) ?>">
                </div>
                <div class="form-row"><label>Phone</label><input type="text" name="phone" required></div>
                <div class="form-row"><label>City</label><input type="text" name="city" required></div>
                <div class="form-row" style="grid-column: span 2;"><label>Address line</label><input type="text" name="line1" required></div>
                <div class="form-row"><label>State</label><input type="text" name="state"></div>
                <div class="form-row"><label>Postal code</label><input type="text" name="postal_code"></div>
                <div class="form-row" style="grid-column: span 2;">
                    <label>Country</label>
                    <select name="country_code" required>
                        <?php foreach (Database::all("SELECT * FROM countries ORDER BY country_name") as $c): ?>
                            <option value="<?= e($c['country_code']) ?>" <?= ($c['country_code'] === ($user['country'] ?? 'ID')) ? 'selected' : '' ?>>
                                <?= e($c['country_name']) ?> (tax <?= number_format($c['tax_rate'] * 100, 1) ?>%)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-outline" type="submit" style="grid-column: span 2;">Save address</button>
            </form>
        </div>

        <!-- Items -->
        <div class="card mt-2">
            <h3>🧺 Items</h3>
            <?php foreach ($items as $it): ?>
                <div class="row" style="justify-content:space-between; padding: 8px 0; border-bottom: 1px solid var(--border);">
                    <div>
                        <div class="fw-600"><?= e($it['product_name']) ?></div>
                        <div class="text-muted fs-13"><?= (int) $it['qty'] ?> × <?= Currency::display((float) $it['price'], $it['currency_code'], $displayCur) ?> · Seller: <?= e($it['shop_name']) ?></div>
                    </div>
                    <div class="fw-bold"><?= Currency::display((float) $it['subtotal'], $it['currency_code'], $displayCur) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Summary -->
    <div>
        <div class="card" style="position:sticky; top: calc(var(--topbar-h) + 16px);">
            <h3>💳 Payment summary</h3>
            <div class="row" style="justify-content:space-between;"><span>Subtotal</span><span><?= Currency::format($subtotal, $displayCur) ?></span></div>
            <div class="row" style="justify-content:space-between;"><span>Shipping</span><span><?= Currency::format($shipping, $displayCur) ?></span></div>
            <div class="row" style="justify-content:space-between;"><span>Tax (<?= number_format(($country['tax_rate'] ?? 0) * 100, 1) ?>%)</span><span><?= Currency::format($tax, $displayCur) ?></span></div>
            <div class="divider"></div>
            <div class="row" style="justify-content:space-between;">
                <span class="fw-bold">Total</span>
                <span class="fw-bold" style="color:var(--color-primary); font-size:20px;"><?= Currency::format($total, $displayCur) ?></span>
            </div>
            <button class="btn btn-primary btn-block btn-lg mt-2" type="submit" form="placeOrderForm">Place order</button>
            <div class="text-soft fs-13 mt-1" style="text-align:center;">Funds are held in an escrow simulation until delivery.</div>
        </div>
    </div>
</div>
<?php layout('footer'); ?>
