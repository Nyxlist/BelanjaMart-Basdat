<?php
/**
 * Re-usable product card.
 *   component('product_card', ['p' => $row, 'displayCurrency' => 'IDR'])
 */
$display = $displayCurrency ?? ($p['currency_code'] ?? 'IDR');
$priceText = Currency::display(
    (float) $p['price'],
    $p['currency_code'] ?? 'IDR',
    $display
);
$wished = !empty($p['_wished']);
?>
<div class="product-card">
    <a href="<?= base_url('/frontend/pages/shared/product_detail.php?id=' . (int) $p['product_id']) ?>" style="color:inherit;">
        <div class="img">
            <?php if (!empty($p['image'])): ?>
                <img src="<?= base_url('/storage/uploads/' . e($p['image'])) ?>" alt="<?= e($p['product_name']) ?>" style="object-fit:cover; width:100%; height:100%;">
            <?php else: ?>
                <?= e($p['category_icon'] ?? '📦') ?>
            <?php endif; ?>

            <?php if (current_user() && (current_user()['role'] ?? null) === 'buyer'): ?>
                <form method="POST" action="<?= base_url('/frontend/pages/buyer/wishlist.php') ?>" style="position:absolute; top:8px; right:8px;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
                    <input type="hidden" name="action" value="toggle">
                    <button type="submit" class="wish-btn <?= $wished ? 'active' : '' ?>" title="Wishlist">
                        <?= $wished ? '❤️' : '🤍' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <div class="body">
            <div class="name"><?= e($p['product_name']) ?></div>
            <div class="price"><?= $priceText ?></div>
            <div class="meta">⭐ <?= number_format((float) $p['average_rating'], 1) ?>
                (<?= (int) $p['total_reviews'] ?>) · Sold <?= (int) ($p['total_sold'] ?? 0) ?></div>
            <div class="meta">Seller: <?= e($p['shop_name'] ?? $p['seller_name'] ?? '-') ?>
                <?php if (!empty($p['seller_verified'])): ?> ✅<?php endif; ?>
            </div>
        </div>
    </a>
    <div class="body" style="padding-top:0;">
        <div class="actions">
            <a href="<?= base_url('/frontend/pages/buyer/cart.php?add=' . (int) $p['product_id']) ?>" class="btn btn-primary btn-sm btn-block">🛒 Add to Cart</a>
        </div>
    </div>
</div>
