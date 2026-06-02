<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('buyer');

$user = current_user();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) { flash('err', 'Session expired, please try again.'); redirect('/frontend/pages/buyer/wishlist.php'); }
    $pid = (int) ($_POST['product_id'] ?? 0);
    if ($_POST['action'] === 'toggle' && $pid > 0) {
        if (WishlistModel::has((int) $user['user_id'], $pid)) {
            WishlistModel::remove((int) $user['user_id'], $pid);
            flash('ok', 'Removed from wishlist');
        } else {
            WishlistModel::add((int) $user['user_id'], $pid);
            flash('ok', 'Added to wishlist');
        }
    }
    redirect($_SERVER['HTTP_REFERER'] ?? '/frontend/pages/buyer/wishlist.php');
}

$items = WishlistModel::listFor((int) $user['user_id']);
$displayCur = $user['currency'] ?? 'IDR';

layout('header', ['title' => 'Wishlist']);
?>
<?php component('flash'); ?>
<h2 class="mb-2">My Wishlist</h2>

<?php if (empty($items)): ?>
    <div class="card center" style="padding:50px; flex-direction:column;">
        <div style="font-size:50px; color:var(--text-soft);">—</div>
        <p class="text-muted">No items in your wishlist yet.</p>
        <a class="btn btn-primary" href="<?= base_url('/frontend/pages/buyer/home.php') ?>">Discover products</a>
    </div>
<?php else: ?>
<div class="grid grid-cards">
    <?php foreach ($items as $p):
        $p['_wished'] = true;
        component('product_card', ['p' => $p, 'displayCurrency' => $displayCur]);
    endforeach; ?>
</div>
<?php endif; ?>
<?php layout('footer'); ?>
