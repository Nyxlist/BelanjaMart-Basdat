<?php
/**
 * ChatService - thin glue around ChatModel.  Most logic is in the
 * model; this layer adds permission checks and notifications.
 */

class ChatService
{
    public static function openRoom(int $buyerId, int $sellerId, ?int $orderId = null): array
    {
        return ChatModel::findOrCreate($buyerId, $sellerId, $orderId);
    }

    public static function userIsParticipant(int $chatId, int $userId): bool
    {
        $c = ChatModel::find($chatId);
        if (!$c) return false;
        return (int) $c['buyer_id'] === $userId || (int) $c['seller_id'] === $userId;
    }

    public static function send(int $chatId, int $senderId, string $body, ?array $upload = null): array
    {
        if (!self::userIsParticipant($chatId, $senderId)) {
            return ['ok' => false, 'message' => 'Forbidden'];
        }

        $attachment = null;
        $type       = null;
        if ($upload && !empty($upload['tmp_name']) && is_uploaded_file($upload['tmp_name'])) {
            // 5 MB cap
            if (($upload['size'] ?? 0) > 5 * 1024 * 1024) {
                return ['ok' => false, 'message' => 'File too large (max 5MB)'];
            }
            $ext   = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
            $allow = ['jpg','jpeg','png','gif','webp','pdf','txt'];
            if (!in_array($ext, $allow, true)) {
                return ['ok' => false, 'message' => 'File type not allowed'];
            }
            $name  = 'chat_' . $chatId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dir   = config('storage.chat');
            if (!is_dir($dir)) @mkdir($dir, 0777, true);
            move_uploaded_file($upload['tmp_name'], $dir . '/' . $name);
            $attachment = 'chat/' . $name;          // relative path under /storage
            $type       = in_array($ext, ['jpg','jpeg','png','gif','webp'], true) ? 'image' : 'file';
        }

        $body = trim($body);
        if ($body === '' && !$attachment) {
            return ['ok' => false, 'message' => 'Message is empty'];
        }

        $messageId = ChatModel::send($chatId, $senderId, $body, $attachment, $type);

        // Notify the other participant
        $chat   = ChatModel::find($chatId);
        $other  = (int) $chat['buyer_id'] === $senderId
                    ? (int) $chat['seller_id']
                    : (int) $chat['buyer_id'];
        $sender = UserModel::findById($senderId);
        NotificationModel::push($other, 'New message from ' . $sender['name'],
            mb_substr($body, 0, 80) ?: '[attachment]',
            '💬',
            '/frontend/pages/shared/chat.php?chat_id=' . $chatId);

        return ['ok' => true, 'message_id' => $messageId];
    }

    public static function markRead(int $chatId, int $userId): void
    {
        if (self::userIsParticipant($chatId, $userId)) {
            ChatModel::markRead($chatId, $userId);
        }
    }

    public static function pollMessages(int $chatId, int $userId, int $afterId): array
    {
        if (!self::userIsParticipant($chatId, $userId)) {
            return ['messages' => [], 'typing' => []];
        }
        $messages = ChatModel::messages($chatId, $afterId);
        $typing   = ChatModel::whoIsTyping($chatId, $userId);
        ChatModel::markRead($chatId, $userId);
        return ['messages' => $messages, 'typing' => $typing];
    }
}
