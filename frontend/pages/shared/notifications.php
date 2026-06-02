<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb();

$user = current_user();
$rows = NotificationModel::listFor((int) $user['user_id'], 50);
NotificationModel::markAllRead((int) $user['user_id']);

layout('header', ['title' => 'Notifications']);
?>
<h2 class="mb-2">Notifications</h2>

<div class="card">
    <?php if (empty($rows)): ?>
        <p class="text-muted">No notifications yet.</p>
    <?php else: foreach ($rows as $n): ?>
        <a href="<?= e($n['link'] ?? '#') ?>" style="display:block; padding: 10px 0; border-bottom: 1px solid var(--border); color:inherit;">
            <div class="row" style="gap:10px; align-items:flex-start;">
                <div style="font-size:24px;"><?= e($n['icon']) ?></div>
                <div style="flex:1;">
                    <div class="fw-600"><?= e($n['title']) ?></div>
                    <div class="text-muted fs-13"><?= e($n['body']) ?></div>
                    <div class="text-soft fs-13"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></div>
                </div>
            </div>
        </a>
    <?php endforeach; endif; ?>
</div>
<?php layout('footer'); ?>
