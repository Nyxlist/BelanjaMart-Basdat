<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb();

$user    = current_user();
$userId  = (int) $user['user_id'];
$rooms   = ChatModel::listForUser($userId);

// Open or create a room from the URL (?seller_id=N or ?chat_id=N)
$active = null;
if (!empty($_GET['chat_id'])) {
    $c = ChatModel::find((int) $_GET['chat_id']);
    if ($c && ((int) $c['buyer_id'] === $userId || (int) ($c['seller_id'] ?? 0) === $userId)) {
        $active = $c;
    }
} elseif (!empty($_GET['seller_id']) && $user['role'] === 'buyer') {
    $active = ChatService::openRoom($userId, (int) $_GET['seller_id'], !empty($_GET['order_id']) ? (int) $_GET['order_id'] : null);
} elseif (!empty($rooms)) {
    $active = ChatModel::find((int) $rooms[0]['chat_id']);
}

$messages = $active ? ChatModel::messages((int) $active['chat_id']) : [];
if ($active) ChatModel::markRead((int) $active['chat_id'], $userId);

$other = null;
if ($active) {
    $otherId = (int) $active['buyer_id'] === $userId ? (int) $active['seller_id'] : (int) $active['buyer_id'];
    $other   = UserModel::findById($otherId);
}

// Issue a quick JWT so the JS can hit the API while the user has only a session
$token = AuthService::issueToken($user);

layout('header', ['title' => 'Messages']);
?>
<?php component('flash'); ?>
<div class="chat-shell">
    <!-- LEFT: room list -->
    <div class="chat-list">
        <div style="padding: 14px 16px; border-bottom: 1px solid var(--border);">
            <h3>Messages</h3>
        </div>
        <?php if (empty($rooms)): ?>
            <div class="text-muted" style="padding:16px; text-align:center;">No conversations yet.</div>
        <?php else: foreach ($rooms as $r):
            $isMine   = (int) $r['buyer_id'] === $userId;
            $name     = $isMine ? $r['seller_name'] : $r['buyer_name'];
            $isActive = $active && (int) $r['chat_id'] === (int) $active['chat_id'];
        ?>
            <a class="<?= $isActive ? 'active' : '' ?>" href="?chat_id=<?= (int) $r['chat_id'] ?>">
                <div class="row" style="justify-content:space-between;">
                    <span class="fw-600"><?= e($name) ?></span>
                    <?php if ($r['unread_count']): ?><span class="badge-count"><?= (int) $r['unread_count'] ?></span><?php endif; ?>
                </div>
                <div class="text-muted fs-13" style="white-space: nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= e(mb_substr($r['last_message'] ?? 'New conversation', 0, 60)) ?>
                </div>
            </a>
        <?php endforeach; endif; ?>
    </div>

    <!-- RIGHT: room view -->
    <div class="chat-room">
        <?php if (!$active): ?>
            <div class="center" style="flex:1; flex-direction:column; color:var(--text-muted);">
                <div style="font-size:18px; font-weight:600;">No conversation selected</div>
                Select a conversation
            </div>
        <?php else: ?>
            <div class="chat-header"><?= e($other['name'] ?? 'Conversation') ?>
                <?php if ($active['order_id']): ?> · <a href="<?= base_url('/frontend/pages/' . ($user['role'] === 'seller' ? 'seller' : 'buyer') . '/orders.php') ?>">Order #<?= (int) $active['order_id'] ?></a><?php endif; ?>
            </div>
            <div class="chat-body" id="chatBody" data-last-id="<?= !empty($messages) ? (int) end($messages)['message_id'] : 0 ?>">
                <?php foreach ($messages as $m):
                    $mine = (int) $m['sender_id'] === $userId; ?>
                    <div class="chat-msg <?= $mine ? 'mine' : '' ?>">
                        <?php if (!$mine): ?><div class="fw-600 fs-12"><?= e($m['sender_name']) ?></div><?php endif; ?>
                        <?php if ($m['body']): ?><div><?= nl2br(e($m['body'])) ?></div><?php endif; ?>
                        <?php if ($m['attachment']):
                            $url = base_url('/storage/' . $m['attachment']);
                            if ($m['attachment_type'] === 'image'): ?>
                                <img src="<?= e($url) ?>" alt="attachment">
                            <?php else: ?>
                                <a href="<?= e($url) ?>" target="_blank">Attachment</a>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="meta"><?= date('H:i', strtotime($m['created_at'])) ?>
                            <?php if ($mine): ?><?= $m['is_read'] ? ' ✓✓' : ' ✓' ?><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="typing-indicator" id="typingIndicator"></div>
            <form class="chat-form" id="chatForm" enctype="multipart/form-data">
                <input type="text" id="chatInput" name="body" placeholder="Type a message..." autocomplete="off">
                <label class="btn btn-ghost btn-sm" style="margin:0; cursor:pointer;">Attach
                    <input type="file" name="attachment" accept="image/*,.pdf,.txt" style="display:none;">
                </label>
                <button class="btn btn-primary btn-sm">Send</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($active): ?>
<script>
window.BM_TOKEN = <?= json_encode($token) ?>;
window.BM_CHAT  = {
    chatId: <?= (int) $active['chat_id'] ?>,
    currentUserId: <?= $userId ?>,
    apiBase: <?= json_encode(base_url('/backend/api/v1')) ?>
};
</script>
<script src="<?= asset('js/chat.js') ?>"></script>
<?php endif; ?>

<?php layout('footer'); ?>
