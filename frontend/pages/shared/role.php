<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::guestOnly();

layout('header', ['title' => __('Sign in')]);
?>
<div class="container-narrow" style="padding-top: 40px;">
    <?php component('flash'); ?>

    <div class="card" style="text-align:center;">
        <h1 style="font-size:28px;"><?= e(__('Welcome to BelanjaMart')) ?></h1>
        <p class="text-muted" style="margin: 8px 0 24px;">
            <?= e(__('A modern marketplace - sign in below to continue.')) ?>
        </p>

        <div class="row" style="justify-content:center; flex-wrap:wrap;">
            <a href="<?= base_url('/auth/login.php?role=buyer') ?>" class="card" style="flex:1; min-width:220px; text-align:center; text-decoration:none;">
                <div style="font-size:48px; color:var(--color-primary);">B</div>
                <h3><?= e(__('Shop as Buyer')) ?></h3>
                <p class="text-muted fs-13"><?= e(__('Discover quality products from trusted sellers.')) ?></p>
                <span class="btn btn-primary btn-block"><?= e(__('Continue as Buyer')) ?></span>
            </a>
            <a href="<?= base_url('/auth/login.php?role=seller') ?>" class="card" style="flex:1; min-width:220px; text-align:center; text-decoration:none;">
                <div style="font-size:48px; color:var(--color-primary);">S</div>
                <h3><?= e(__('Sell on BelanjaMart')) ?></h3>
                <p class="text-muted fs-13"><?= e(__('List your products to a global audience.')) ?></p>
                <span class="btn btn-outline btn-block"><?= e(__('Continue as Seller')) ?></span>
            </a>
        </div>
        <div class="divider"></div>
        <p class="text-muted fs-13">
            <?= e(__('Demo accounts')) ?> (password: <code>password123</code>):<br>
            buyer1@mail.com · seller1@mail.com · admin@mail.com
        </p>
    </div>
</div>
<?php layout('footer'); ?>
