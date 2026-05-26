<?php
/**
 * RecommendationService - rating/popularity-based recommender.
 *
 * Two strategies:
 *  1. "Top picks for you" - based on the user's past categories
 *  2. "Trending now"      - top sold + highest rated globally
 *
 * Strict ML is out of scope.  This is enough for a believable demo
 * and is easy to extend later (e.g. content-based or collab filter).
 */

class RecommendationService
{
    public static function topPicks(int $userId, int $limit = 8): array
    {
        // Categories the user has bought from
        $cats = Database::all("
            SELECT DISTINCT p.category_id
            FROM order_items oi
            JOIN orders o   ON o.order_id = oi.order_id
            JOIN products p ON p.product_id = oi.product_id
            WHERE o.user_id = ?
        ", [$userId]);

        if (empty($cats)) {
            return self::trending($limit);
        }

        $ids = array_column($cats, 'category_id');
        $in  = implode(',', array_map('intval', $ids));

        return Database::all("
            SELECT p.*, u.name AS seller_name, sp.shop_name,
                   (p.average_rating * 0.7 + LEAST(p.total_sold/100,1) * 0.3) AS score
            FROM products p
            JOIN users u            ON u.user_id    = p.seller_id
            LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
            WHERE p.is_active = 1
              AND p.stock > 0
              AND p.category_id IN ($in)
              AND p.product_id NOT IN (
                  SELECT oi.product_id FROM order_items oi
                  JOIN orders o ON o.order_id = oi.order_id
                  WHERE o.user_id = ?
              )
            ORDER BY score DESC, p.average_rating DESC
            LIMIT $limit
        ", [$userId]);
    }

    public static function trending(int $limit = 8): array
    {
        return Database::all("
            SELECT p.*, u.name AS seller_name, sp.shop_name
            FROM products p
            JOIN users u            ON u.user_id    = p.seller_id
            LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
            WHERE p.is_active = 1 AND p.stock > 0
            ORDER BY p.total_sold DESC, p.average_rating DESC
            LIMIT $limit
        ");
    }

    /** "Customers also bought" / similar products. */
    public static function similar(int $productId, int $limit = 6): array
    {
        $p = ProductModel::find($productId);
        if (!$p) return [];

        return Database::all("
            SELECT p.*, u.name AS seller_name, sp.shop_name
            FROM products p
            JOIN users u            ON u.user_id    = p.seller_id
            LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
            WHERE p.is_active = 1
              AND p.stock > 0
              AND p.product_id <> ?
              AND p.category_id = ?
            ORDER BY p.average_rating DESC, p.total_sold DESC
            LIMIT $limit
        ", [$productId, $p['category_id']]);
    }
}
