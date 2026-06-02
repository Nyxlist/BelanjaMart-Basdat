<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';

$user = current_user();
$id   = (int) ($_GET['id'] ?? 0);
$p    = ProductModel::find($id);
if (!$p) { http_response_code(404); echo 'Product not found'; exit; }

ProductModel::incrementViews($id);
$reviews = ReviewModel::forProduct($id);
$similar = RecommendationService::similar($id);
$badges  = UserModel::badges((int) $p['seller_id']);
$wished  = ($user && ($user['role'] ?? null) === 'buyer') ? WishlistModel::has((int) $user['user_id'], $id) : false;
$displayCur = $user ? ($user['currency'] ?? 'IDR') : 'IDR';

layout('header', ['title' => $p['product_name']]);
?>
<?php component('flash'); ?>

<div class="grid" style="grid-template-columns: 1.4fr 1fr; gap: 24px;">
    <div class="card center" style="background:var(--bg-muted); min-height:340px; font-size:48px; color:var(--text-soft);">
        <?= e($p['category_icon'] ?? '') ?>
    </div>
    <div class="card">
        <span class="tag tag-primary"><?= e($p['category_name']) ?></span>
        <h2 class="mt-1"><?= e($p['product_name']) ?></h2>
        <div class="text-muted fs-13">★ <?= number_format((float) $p['average_rating'], 1) ?> · <?= (int) $p['total_reviews'] ?> reviews · Sold <?= (int) $p['total_sold'] ?></div>
        <div style="font-size: 28px; font-weight: 800; color: var(--color-primary); margin: 12px 0;">
            <?= Currency::display((float) $p['price'], $p['currency_code'], $displayCur) ?>
        </div>
        <?php if ($displayCur !== $p['currency_code']): ?>
            <div class="text-muted fs-13">Sold in <?= e($p['currency_code']) ?>: <?= Currency::format((float) $p['price'], $p['currency_code']) ?></div>
        <?php endif; ?>
        <p class="mt-2"><?= nl2br(e($p['description'] ?? '')) ?></p>
        <div class="row mt-2" style="gap:8px;">
            <?php if ($user && $user['role'] === 'buyer' && $p['stock'] > 0): ?>
                <a class="btn btn-primary btn-lg" href="<?= base_url('/frontend/pages/buyer/cart.php?add=' . (int) $p['product_id']) ?>">Add to cart</a>
                <form method="POST" action="<?= base_url('/frontend/pages/buyer/wishlist.php') ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
                    <input type="hidden" name="action" value="toggle">
                    <button class="btn btn-outline btn-lg"><?= $wished ? '♥ Wishlisted' : '♡ Wishlist' ?></button>
                </form>
                <a class="btn btn-info btn-lg" href="<?= base_url('/frontend/pages/shared/chat.php?seller_id=' . (int) $p['seller_id']) ?>">Chat seller</a>
            <?php elseif ($p['stock'] <= 0): ?>
                <span class="tag tag-danger">Out of stock</span>
            <?php elseif (!$user): ?>
                <a class="btn btn-primary btn-lg" href="<?= base_url('/auth/role.php') ?>">Sign in to buy</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Seller card -->
<div class="card mt-3">
    <h3>About the seller</h3>
    <div class="row" style="gap:16px; flex-wrap:wrap;">
        <div style="width:64px; height:64px; border-radius:50%; background:var(--bg-muted); display:flex; align-items:center; justify-content:center; font-size:24px;">
            <?= e(mb_strtoupper(mb_substr($p['shop_name'] ?? 'S', 0, 1))) ?>
        </div>
        <div style="flex:1; min-width:240px;">
            <div class="fw-bold fs-18"><?= e($p['shop_name'] ?? $p['seller_name']) ?> <?php if (!empty($p['seller_verified'])): ?><span class="tag tag-success" style="font-size:10px; padding:1px 5px;">Verified</span><?php endif; ?></div>
            <div class="text-muted fs-13">★ <?= number_format((float) ($p['seller_rating'] ?? 0), 2) ?>
                · <?= (int) ($p['seller_total_reviews'] ?? 0) ?> reviews
                · Avg response <?= (int) ($p['response_time_min'] ?? 0) ?>m
                · Cancellation rate <?= number_format((float) ($p['cancellation_rate'] ?? 0), 1) ?>%</div>
            <?php component('seller_badge', ['badges' => $badges]); ?>
        </div>
        <a class="btn btn-outline" href="<?= base_url('/frontend/pages/shared/seller_profile.php?id=' . (int) $p['seller_id']) ?>">Visit shop →</a>
    </div>
</div>

<!-- Reviews -->
<div class="card mt-3">
    <h3>Customer reviews</h3>
    <?php if (empty($reviews)): ?>
        <p class="text-muted">No reviews yet — be the first!</p>
    <?php else: foreach ($reviews as $r): ?>
        <div style="border-bottom:1px solid var(--border); padding: 10px 0;">
            <div class="row" style="justify-content:space-between;">
                <div class="fw-600"><?= e($r['user_name']) ?></div>
                <div>★ <?= (int) $r['rating'] ?>/5</div>
            </div>
            <div class="text-soft fs-13"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></div>
            <p><?= nl2br(e($r['comment'])) ?></p>
        </div>
    <?php endforeach; endif; ?>
</div>

<!-- Similar -->
<?php if (!empty($similar)): ?>
<h3 class="mt-3 mb-2">Similar products</h3>
<div class="grid grid-cards">
    <?php foreach ($similar as $sp) component('product_card', ['p' => $sp, 'displayCurrency' => $displayCur]); ?>
</div>
<?php endif; ?>

<?php layout('footer'); ?>
