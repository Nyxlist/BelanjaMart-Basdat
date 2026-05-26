<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::guestOnly();

$role = $_GET['role'] ?? 'buyer';
if (!in_array($role, ['buyer', 'seller'], true)) $role = 'buyer';

$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) {
        flash('err', 'Invalid form session.');
        redirect('/auth/register.php?role=' . $role);
    }
    $res = AuthService::register($_POST, $role);
    if ($res['ok']) {
        flash('ok', 'Account created! You can sign in now.');
        redirect('/auth/login.php?role=' . $role);
    }
    $err = $res['message'];
}

layout('header', ['title' => 'Create account']);
?>
<div class="container-narrow" style="padding-top: 40px;">
    <div class="card">
        <a class="text-muted fs-13" href="<?= base_url('/auth/role.php') ?>">← Change role</a>
        <h2 style="margin-top:8px;">Create your <?= e($role) ?> account</h2>

        <?php if ($err): ?>
            <div class="toast toast-danger" style="position:relative; margin-bottom:12px;">⚠️ <?= e($err) ?></div>
        <?php endif; ?>

        <form method="POST" class="mt-2">
            <?= csrf_field() ?>
            <div class="form-row">
                <label>Full name</label>
                <input type="text" name="name" required maxlength="100" value="<?= e($_POST['name'] ?? '') ?>">
            </div>
            <?php if ($role === 'seller'): ?>
            <div class="form-row">
                <label>Shop name</label>
                <input type="text" name="shop_name" maxlength="120" value="<?= e($_POST['shop_name'] ?? '') ?>">
                <div class="text-soft fs-13">Leave blank to reuse your full name.</div>
            </div>
            <?php endif; ?>
            <div class="form-row">
                <label>Email</label>
                <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label>Password</label>
                <input type="password" name="password" minlength="6" required>
                <div class="text-soft fs-13">Minimum 6 characters.</div>
            </div>
            <div class="form-row">
                <label>Country</label>
                <select name="country_code">
                    <?php foreach (Database::all("SELECT * FROM countries ORDER BY country_name") as $c): ?>
                        <option value="<?= e($c['country_code']) ?>" <?= ($c['country_code'] === 'ID') ? 'selected' : '' ?>>
                            <?= e($c['country_name']) ?> (<?= e($c['currency_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit">Create account</button>
        </form>

        <div class="divider"></div>
        <p class="text-muted fs-13">
            Already have an account? <a href="<?= base_url('/auth/login.php?role=' . $role) ?>">Sign in</a>.
        </p>
    </div>
</div>
<?php layout('footer'); ?>
