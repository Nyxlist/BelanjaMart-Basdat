<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('buyer');

$user        = current_user();
$orderItemId = (int) ($_GET['order_item_id'] ?? 0);

// Verify the buyer actually purchased and received this item
$item = Database::one("
    SELECT oi.*, p.product_name, o.status, o.user_id AS buyer_id
    FROM order_items oi
    JOIN orders   o ON oi.order_id   = o.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_item_id = ?
", [$orderItemId]);

if (!$item || (int) $item['buyer_id'] !== (int) $user['user_id']) {
    http_response_code(404); echo 'Order item not found.'; exit;
}
if ($item['status'] !== 'delivered') {
    flash('err', 'You can only review delivered items.');
    redirect('/frontend/pages/buyer/orders.php');
}

$existing = ReviewModel::findByOrderItem($orderItemId);

if (!$existing && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) { flash('err', 'Invalid session'); redirect('/frontend/pages/shared/review.php?order_item_id=' . $orderItemId); }
    $rating = (int) ($_POST['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        flash('err', 'Rating must be between 1 and 5.');
    } else {
        try {
            ReviewModel::create($orderItemId, (int) $user['user_id'], $rating,
                (string) ($_POST['comment'] ?? ''),
                !empty($_POST['seller_rating']) ? (int) $_POST['seller_rating'] : null);
            ReputationService::recompute((int) $item['seller_id']);
            flash('ok', 'Review submitted, thanks!');
            redirect('/frontend/pages/buyer/orders.php');
        } catch (\Throwable $e) {
            flash('err', 'Failed to submit: ' . $e->getMessage());
        }
    }
}

layout('header', ['title' => 'Write review']);
?>
<?php component('flash'); ?>
<div class="container-narrow">
    <div class="card">
        <h2>⭐ Review: <?= e($item['product_name']) ?></h2>
        <?php if ($existing): ?>
            <p class="text-muted">You already submitted this review on <?= date('d M Y', strtotime($existing['created_at'])) ?>.</p>
            <div class="row" style="gap:6px;"><span>Rating:</span><span>⭐ <?= (int) $existing['rating'] ?>/5</span></div>
            <p class="mt-1"><?= nl2br(e($existing['comment'])) ?></p>
            <a class="btn btn-outline mt-2" href="<?= base_url('/frontend/pages/buyer/orders.php') ?>">← Back to orders</a>
        <?php else: ?>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="form-row">
                    <label>Product rating</label>
                    <select name="rating" required>
                        <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
                        <option value="4">⭐⭐⭐⭐ Good</option>
                        <option value="3">⭐⭐⭐ OK</option>
                        <option value="2">⭐⭐ Bad</option>
                        <option value="1">⭐ Terrible</option>
                    </select>
                </div>
                <div class="form-row">
                    <label>Seller rating (optional)</label>
                    <select name="seller_rating">
                        <option value="">— skip —</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?= $i ?>"><?= str_repeat('⭐', $i) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Your review</label>
                    <textarea name="comment" required placeholder="What did you like?  What could be better?"></textarea>
                </div>
                <button class="btn btn-primary btn-block">Submit review</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php layout('footer'); ?>
