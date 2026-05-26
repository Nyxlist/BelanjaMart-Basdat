<?php
/**
 * ReviewModel - reviews and product/seller rating reads.
 */

class ReviewModel
{
    public static function findByOrderItem(int $orderItemId): ?array
    {
        return Database::one(
            "SELECT * FROM reviews WHERE order_item_id = ?",
            [$orderItemId]
        );
    }

    public static function forProduct(int $productId, int $limit = 20): array
    {
        return Database::all("
            SELECT r.*, u.name AS user_name
            FROM reviews r
            JOIN order_items oi ON r.order_item_id = oi.order_item_id
            JOIN users u        ON r.user_id = u.user_id
            WHERE oi.product_id = ?
            ORDER BY r.created_at DESC
            LIMIT $limit
        ", [$productId]);
    }

    public static function forSeller(int $sellerId, int $limit = 30): array
    {
        return Database::all("
            SELECT r.*, u.name AS user_name, p.product_name
            FROM reviews r
            JOIN order_items oi ON r.order_item_id = oi.order_item_id
            JOIN products p     ON oi.product_id = p.product_id
            JOIN users u        ON r.user_id = u.user_id
            WHERE oi.seller_id = ?
            ORDER BY r.created_at DESC
            LIMIT $limit
        ", [$sellerId]);
    }

    public static function create(int $orderItemId, int $userId, int $rating, string $comment, ?int $sellerRating = null): int
    {
        return Database::insert('reviews', [
            'order_item_id' => $orderItemId,
            'user_id'       => $userId,
            'rating'        => $rating,
            'seller_rating' => $sellerRating,
            'comment'       => $comment,
        ]);
    }
}
