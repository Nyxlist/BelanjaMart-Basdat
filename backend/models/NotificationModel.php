<?php
/**
 * NotificationModel - in-app notifications shown in the navbar bell.
 */

class NotificationModel
{
    public static function push(int $userId, string $title, string $body, string $icon = '🔔', ?string $link = null): int
    {
        return Database::insert('notifications', [
            'user_id' => $userId,
            'title'   => $title,
            'body'    => $body,
            'icon'    => $icon,
            'link'    => $link,
        ]);
    }

    public static function listFor(int $userId, int $limit = 20): array
    {
        return Database::all(
            "SELECT * FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC LIMIT $limit",
            [$userId]
        );
    }

    public static function unreadCount(int $userId): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }

    public static function markAllRead(int $userId): void
    {
        Database::run(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
    }
}
