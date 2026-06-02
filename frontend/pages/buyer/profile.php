<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('buyer');

$user    = current_user();
$profile = UserModel::buyerProfile((int) $user['user_id']);
$stats   = AnalyticsService::buyerDashboard((int) $user['user_id']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) { flash('err', 'Session expired, please try again.'); redirect('/frontend/pages/buyer/profile.php'); }
    UserModel::updateProfile((int) $user['user_id'], $_POST);
    Database::update('buyer_profiles', ['bio' => $_POST['bio'] ?? ''], 'user_id = ?', [(int) $user['user_id']]);
    $u = UserModel::findById((int) $user['user_id']);
    AuthService::loginSession($u);
    flash('ok', 'Profile updated.');
    redirect('/frontend/pages/buyer/profile.php');
}

layout('header', ['title' => 'My profile']);
?>
<?php component('flash'); ?>
<h2 class="mb-2">My Profile</h2>

<div class="grid" style="grid-template-columns: 1fr 2fr; gap:20px;">
    <div class="card center" style="flex-direction:column;">
        <div style="width:96px; height:96px; border-radius:50%; background:var(--bg-muted); display:flex; align-items:center; justify-content:center; font-size:40px;">
            <?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?>
        </div>
        <h3 class="mt-1"><?= e($user['name']) ?></h3>
        <p class="text-muted fs-13"><?= e($profile['bio'] ?? 'No bio yet.') ?></p>
        <div class="row mt-2" style="gap:6px; flex-wrap:wrap; justify-content:center;">
            <span class="tag tag-info"><?= (int) $stats['orders'] ?> orders</span>
            <span class="tag tag-success"><?= (int) $stats['delivered'] ?> delivered</span>
            <span class="tag tag-primary"><?= (int) $stats['wishlist'] ?> wishlisted</span>
        </div>
    </div>

    <div class="card">
        <h3>Account</h3>
        <form method="POST" class="grid" style="grid-template-columns:1fr 1fr; gap:8px;">
            <?= csrf_field() ?>
            <div class="form-row"><label>Full name</label><input type="text" name="name" value="<?= e($user['name']) ?>" required></div>
            <div class="form-row"><label>Phone</label><input type="text" name="phone" value="<?= e($profile['phone'] ?? '') ?>"></div>
            <div class="form-row"><label>Country</label>
                <select name="country_code">
                    <?php foreach (Database::all("SELECT * FROM countries ORDER BY country_name") as $c): ?>
                        <option value="<?= e($c['country_code']) ?>" <?= ($c['country_code'] === ($user['country'] ?? 'ID')) ? 'selected' : '' ?>>
                            <?= e($c['country_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row"><label>Preferred currency</label>
                <select name="preferred_currency">
                    <?php foreach (Database::all("SELECT * FROM currencies") as $c): ?>
                        <option value="<?= e($c['currency_code']) ?>" <?= ($c['currency_code'] === ($user['currency'] ?? 'IDR')) ? 'selected' : '' ?>>
                            <?= e($c['currency_code']) ?> — <?= e($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row" style="grid-column: span 2;">
                <label>Bio</label>
                <textarea name="bio"><?= e($profile['bio'] ?? '') ?></textarea>
            </div>
            <button class="btn btn-primary" type="submit" style="grid-column: span 2;">Save changes</button>
        </form>
    </div>
</div>
<?php layout('footer'); ?>
