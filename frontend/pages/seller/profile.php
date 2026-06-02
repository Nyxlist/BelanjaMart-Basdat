<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('seller');

$user    = current_user();
$profile = UserModel::sellerProfile((int) $user['user_id']);
$badges  = UserModel::badges((int) $user['user_id']);
$reviews = ReviewModel::forSeller((int) $user['user_id']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) { flash('err', 'Session expired, please try again.'); redirect('/frontend/pages/seller/profile.php'); }
    UserModel::updateProfile((int) $user['user_id'], $_POST);
    UserModel::updateSellerProfile((int) $user['user_id'], $_POST);
    $u = UserModel::findById((int) $user['user_id']);
    AuthService::loginSession($u);
    flash('ok', 'Profile updated.');
    redirect('/frontend/pages/seller/profile.php');
}

layout('header', ['title' => 'Shop profile']);
?>
<?php component('flash'); ?>
<div class="layout">
    <?php component('sidebar_seller'); ?>
    <div>
        <h2>Shop profile</h2>

        <div class="card mt-2">
            <form method="POST" class="grid" style="grid-template-columns: 1fr 1fr; gap:8px;">
                <?= csrf_field() ?>
                <div class="form-row" style="grid-column: span 2;"><label>Full name</label>
                    <input type="text" name="name" value="<?= e($user['name']) ?>" required></div>
                <div class="form-row" style="grid-column: span 2;"><label>Shop name</label>
                    <input type="text" name="shop_name" value="<?= e($profile['shop_name'] ?? '') ?>" required></div>
                <div class="form-row" style="grid-column: span 2;"><label>Description</label>
                    <textarea name="description"><?= e($profile['description'] ?? '') ?></textarea></div>
                <div class="form-row"><label>Country</label>
                    <select name="country_code">
                        <?php foreach (Database::all("SELECT * FROM countries ORDER BY country_name") as $c): ?>
                            <option value="<?= e($c['country_code']) ?>" <?= ($c['country_code'] === ($user['country'] ?? 'ID')) ? 'selected' : '' ?>>
                                <?= e($c['country_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row"><label>Currency</label>
                    <select name="preferred_currency">
                        <?php foreach (Database::all("SELECT * FROM currencies") as $c): ?>
                            <option value="<?= e($c['currency_code']) ?>" <?= ($c['currency_code'] === ($user['currency'] ?? 'IDR')) ? 'selected' : '' ?>>
                                <?= e($c['currency_code']) ?> — <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit" style="grid-column: span 2;">Save changes</button>
            </form>
        </div>

        <div class="card mt-3">
            <h3>Badges</h3>
            <?php if (empty($badges)): ?>
                <p class="text-muted">Earn your first badge by completing more sales and getting reviews.</p>
            <?php else: ?>
                <?php component('seller_badge', ['badges' => $badges]); ?>
            <?php endif; ?>
        </div>

        <div class="card mt-3">
            <h3>Recent reviews</h3>
            <?php if (empty($reviews)): ?>
                <p class="text-muted">No reviews yet.</p>
            <?php else: foreach (array_slice($reviews, 0, 6) as $r): ?>
                <div style="border-bottom:1px solid var(--border); padding: 8px 0;">
                    <div class="row" style="justify-content: space-between;">
                        <div class="fw-600"><?= e($r['user_name']) ?> · <?= e($r['product_name']) ?></div>
                        <span>★ <?= (int) $r['rating'] ?>/5</span>
                    </div>
                    <div class="text-muted fs-13"><?= e($r['comment']) ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php layout('footer'); ?>
