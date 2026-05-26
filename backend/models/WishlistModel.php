<?php
/**
 * WishlistModel - thin CRUD around the `wishlist` table.
 */

class WishlistModel
{
    public static function add(int $userId, int $productId): void
    {
        Database::run("
            INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)
        ", [$userId, $productId]);
    }

    public static function remove(int $userId, int $productId): void
    {
        Database::delete('wishlist', 'user_id = ? AND product_id = ?', [$userId, $productId]);
    }

    public static function has(int $userId, int $productId): bool
    {
        return Database::scalar(
            "SELECT 1 FROM wishlist WHERE user_id = ? AND product_id = ?",
            [$userId, $productId]
        ) ? true : false;
    }

    public static function listFor(int $userId): array
    {
        return Database::all("
            SELECT p.*, w.created_at AS added_at,
                   sp.shop_name, u.name AS seller_name
            FROM wishlist w
            JOIN products p ON p.product_id = w.product_id
            JOIN users u    ON u.user_id    = p.seller_id
            LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
            WHERE w.user_id = ?
            ORDER BY w.created_at DESC
        ", [$userId]);
    }

    public static function count(int $userId): int
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM wishlist WHERE user_id = ?",
            [$userId]
        );
    }
}
