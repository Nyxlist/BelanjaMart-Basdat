<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::guestOnly();

$role = $_GET['role'] ?? 'buyer';
if (!in_array($role, ['buyer', 'seller', 'admin'], true)) $role = 'buyer';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) {
        flash('err', __('Session expired, please try again.'));
        redirect('/auth/login.php?role=' . $role);
    }
    $user = AuthService::attempt(
        (string) ($_POST['email']    ?? ''),
        (string) ($_POST['password'] ?? ''),
        $role
    );
    if (!$user) {
        flash('err', __('Email or password is incorrect.'));
        $_SESSION['old_email'] = $_POST['email'] ?? '';
        redirect('/auth/login.php?role=' . $role);
    }
    AuthService::loginSession($user);
    flash('ok', __('Welcome back, :name!', ['name' => $user['name']]));
    if ($role === 'seller') redirect('/frontend/pages/seller/dashboard.php');
    if ($role === 'admin')  redirect('/frontend/pages/admin/dashboard.php');
    redirect('/frontend/pages/buyer/home.php');
}

$old_email = $_SESSION['old_email'] ?? '';
unset($_SESSION['old_email']);

layout('header', ['title' => __('Sign in')]);
?>
<div class="container-narrow" style="padding-top: 40px;">
    <?php component('flash'); ?>

    <div class="card">
        <a class="text-muted fs-13" href="<?= base_url('/auth/role.php') ?>">← <?= e(__('Change role')) ?></a>
        <h2 style="margin-top:8px;"><?= e(__('Sign in as :role', ['role' => ucfirst($role)])) ?></h2>
        <p class="text-muted fs-13"><?= e(__('Enter your credentials to continue.')) ?></p>

        <form method="POST" class="mt-2">
            <?= csrf_field() ?>
            <div class="form-row">
                <label><?= e(__('Email')) ?></label>
                <input type="email" name="email" value="<?= e($old_email) ?>" required autofocus>
            </div>
            <div class="form-row">
                <label><?= e(__('Password')) ?></label>
                <input type="password" name="password" required>
            </div>
            <button class="btn btn-primary btn-block btn-lg" type="submit"><?= e(__('Sign in')) ?></button>
        </form>

        <div class="divider"></div>
        <p class="text-muted fs-13">
            <?= e(__('New here?')) ?>
            <a href="<?= base_url('/auth/register.php?role=' . $role) ?>"><?= e(__('Create an account')) ?></a>.
        </p>
    </div>
</div>
<?php layout('footer'); ?>
