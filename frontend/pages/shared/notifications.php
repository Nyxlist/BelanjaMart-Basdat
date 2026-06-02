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
    <?php else: foreach ($rows as $n):
        $link = trim($n['link'] ?? '');
        if ($link) {
            $base = rtrim(config('app.base_url', ''), '/');
            // If link already includes the base path, don't double it
            if ($base && strpos($link, $base) === 0) {
                // already has base_url prefix, use as-is
            } else {
                $link = base_url($link);
            }
        }
    ?>
        <a href="<?= e($link ?: '#') ?>" style="display:block; padding: 10px 0; border-bottom: 1px solid var(--border); color:inherit;">
            <div style="flex:1;">
                <div class="fw-600"><?= e($n['title']) ?></div>
                <div class="text-muted fs-13"><?= e($n['body']) ?></div>
                <div class="text-soft fs-13"><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></div>
            </div>
        </a>
    <?php endforeach; endif; ?>
</div>
<?php layout('footer'); ?>
