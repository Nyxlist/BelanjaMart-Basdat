<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb();

$user    = current_user();
$id      = (int) ($_GET['id'] ?? 0);
$profile = UserModel::sellerProfile($id);
if (!$profile) { http_response_code(404); echo 'Seller not found.'; exit; }

$badges  = UserModel::badges($id);
$products= ProductModel::bySeller($id);
$reviews = ReviewModel::forSeller($id, 8);
$displayCur = $user['currency'] ?? 'IDR';

layout('header', ['title' => $profile['shop_name']]);
?>
<div class="card" style="background: linear-gradient(120deg, var(--color-primary), #ff8a65); color:#fff;">
    <h2><?= e($profile['shop_name']) ?> <?php if ($profile['is_verified']): ?><span class="tag tag-success" style="font-size:11px; padding:2px 8px; vertical-align:middle;">Verified</span><?php endif; ?></h2>
    <p style="opacity:.95;"><?= e($profile['description'] ?? '') ?></p>
    <?php component('seller_badge', ['badges' => $badges]); ?>
</div>

<div class="grid mt-3" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
    <div class="card"><div class="text-muted fs-13">Rating</div><div class="fw-bold" style="font-size:22px;">★ <?= number_format((float) $profile['avg_rating'], 2) ?> / 5</div></div>
    <div class="card"><div class="text-muted fs-13">Reviews</div><div class="fw-bold" style="font-size:22px;"><?= (int) $profile['total_reviews'] ?></div></div>
    <div class="card"><div class="text-muted fs-13">Sales</div><div class="fw-bold" style="font-size:22px;"><?= (int) $profile['total_sales'] ?></div></div>
    <div class="card"><div class="text-muted fs-13">Cancellation</div><div class="fw-bold text-danger" style="font-size:22px;"><?= number_format((float) $profile['cancellation_rate'], 2) ?>%</div></div>
    <div class="card"><div class="text-muted fs-13">Avg response</div><div class="fw-bold" style="font-size:22px;"><?= (int) $profile['response_time_min'] ?>m</div></div>
</div>

<div class="row mt-3">
    <?php if ($user['role'] === 'buyer'): ?>
        <a class="btn btn-info" href="<?= base_url('/frontend/pages/shared/chat.php?seller_id=' . $id) ?>">Chat with seller</a>
    <?php endif; ?>
</div>

<h3 class="mt-3 mb-2">Products</h3>
<?php if (empty($products)): ?>
    <p class="text-muted">No products yet.</p>
<?php else: ?>
<div class="grid grid-cards">
    <?php foreach ($products as $p) component('product_card', ['p' => $p, 'displayCurrency' => $displayCur]); ?>
</div>
<?php endif; ?>

<h3 class="mt-3 mb-2">Recent reviews</h3>
<div class="card">
<?php if (empty($reviews)): ?>
    <p class="text-muted">No reviews yet.</p>
<?php else: foreach ($reviews as $r): ?>
    <div style="border-bottom:1px solid var(--border); padding: 8px 0;">
        <div class="row" style="justify-content:space-between;">
            <div class="fw-600"><?= e($r['user_name']) ?> · <?= e($r['product_name']) ?></div>
            <div>★ <?= (int) $r['rating'] ?></div>
        </div>
        <div class="text-muted fs-13"><?= e($r['comment']) ?></div>
    </div>
<?php endforeach; endif; ?>
</div>
<?php layout('footer'); ?>
