<?php
/**
 * ChatModel - persistent chat between buyers and sellers.
 *
 * Real-time delivery is implemented via short polling at the API layer
 * (see backend/api/v1/chat.php).  Websockets would require a separate
 * Node/Ratchet server, which is intentionally out of scope here to keep
 * the stack pure PHP/MySQL.
 */

class ChatModel
{
    /** Find or create a chat room between buyer + seller (optionally tied to an order). */
    public static function findOrCreate(int $buyerId, int $sellerId, ?int $orderId = null): array
    {
        $row = Database::one("
            SELECT * FROM chats
            WHERE buyer_id = ? AND seller_id = ?
              AND ((? IS NULL AND order_id IS NULL) OR order_id = ?)
            LIMIT 1
        ", [$buyerId, $sellerId, $orderId, $orderId]);

        if ($row) return $row;

        $id = Database::insert('chats', [
            'buyer_id'  => $buyerId,
            'seller_id' => $sellerId,
            'order_id'  => $orderId,
        ]);
        return Database::one("SELECT * FROM chats WHERE chat_id = ?", [$id]);
    }

    public static function find(int $chatId): ?array
    {
        return Database::one("SELECT * FROM chats WHERE chat_id = ?", [$chatId]);
    }

    /** All chats a user participates in (most recent first). */
    public static function listForUser(int $userId): array
    {
        return Database::all("
            SELECT c.*,
                   ub.name AS buyer_name,  ub.avatar AS buyer_avatar,
                   us.name AS seller_name, us.avatar AS seller_avatar,
                   (SELECT body FROM chat_messages WHERE chat_id = c.chat_id
                    ORDER BY created_at DESC LIMIT 1) AS last_message,
                   (SELECT COUNT(*) FROM chat_messages
                    WHERE chat_id = c.chat_id AND is_read = 0 AND sender_id <> ?) AS unread_count
            FROM chats c
            JOIN users ub ON ub.user_id = c.buyer_id
            JOIN users us ON us.user_id = c.seller_id
            WHERE c.buyer_id = ? OR c.seller_id = ?
            ORDER BY (c.last_message_at IS NULL), c.last_message_at DESC
        ", [$userId, $userId, $userId]);
    }

    public static function messages(int $chatId, int $afterId = 0, int $limit = 50): array
    {
        return Database::all("
            SELECT m.*, u.name AS sender_name, u.avatar AS sender_avatar
            FROM chat_messages m
            JOIN users u ON u.user_id = m.sender_id
            WHERE m.chat_id = ? AND m.message_id > ?
            ORDER BY m.message_id ASC
            LIMIT $limit
        ", [$chatId, $afterId]);
    }

    public static function send(int $chatId, int $senderId, string $body, ?string $attachment = null, ?string $type = null): int
    {
        $id = Database::insert('chat_messages', [
            'chat_id'         => $chatId,
            'sender_id'       => $senderId,
            'body'            => $body,
            'attachment'      => $attachment,
            'attachment_type' => $type,
        ]);
        Database::run(
            "UPDATE chats SET last_message_at = NOW() WHERE chat_id = ?",
            [$chatId]
        );
        return $id;
    }

    public static function markRead(int $chatId, int $readerId): void
    {
        Database::run("
            UPDATE chat_messages
            SET is_read = 1, read_at = NOW()
            WHERE chat_id = ? AND sender_id <> ? AND is_read = 0
        ", [$chatId, $readerId]);
    }

    /** Upsert a typing indicator (per chat per user). */
    public static function setTyping(int $chatId, int $userId): void
    {
        Database::run("
            INSERT INTO chat_typing (chat_id, user_id) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
        ", [$chatId, $userId]);
    }

    /** Return the *other* participant who started typing within last 5s. */
    public static function whoIsTyping(int $chatId, int $exceptUserId): array
    {
        return Database::all("
            SELECT u.user_id, u.name
            FROM chat_typing ct
            JOIN users u ON u.user_id = ct.user_id
            WHERE ct.chat_id = ? AND ct.user_id <> ?
              AND ct.updated_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
        ", [$chatId, $exceptUserId]);
    }
}
