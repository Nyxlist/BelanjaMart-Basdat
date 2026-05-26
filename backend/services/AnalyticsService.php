<?php
/**
 * AnalyticsService - dashboards for buyers, sellers and admins.
 */

class AnalyticsService
{
    public static function sellerDashboard(int $sellerId): array
    {
        $totalProducts = (int) Database::scalar(
            "SELECT COUNT(*) FROM products WHERE seller_id = ?", [$sellerId]
        );
        $totalStock = (int) Database::scalar(
            "SELECT COALESCE(SUM(stock),0) FROM products WHERE seller_id = ?", [$sellerId]
        );
        $newOrders = (int) Database::scalar("
            SELECT COUNT(DISTINCT o.order_id)
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.order_id
            WHERE oi.seller_id = ? AND o.status IN ('pending','paid','processing')
        ", [$sellerId]);
        $revenue30 = (float) Database::scalar("
            SELECT COALESCE(SUM(oi.quantity * oi.price),0)
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.order_id
            WHERE oi.seller_id = ? AND o.status = 'delivered'
              AND o.delivered_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", [$sellerId]);

        $byDay = Database::all("
            SELECT DATE(o.delivered_at) AS d, SUM(oi.quantity * oi.price) AS revenue
            FROM orders o
            JOIN order_items oi ON oi.order_id = o.order_id
            WHERE oi.seller_id = ? AND o.status = 'delivered'
              AND o.delivered_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
            GROUP BY DATE(o.delivered_at)
            ORDER BY d
        ", [$sellerId]);

        $topProducts = Database::all("
            SELECT p.product_name, p.total_sold, p.average_rating
            FROM products p
            WHERE p.seller_id = ?
            ORDER BY p.total_sold DESC
            LIMIT 5
        ", [$sellerId]);

        $rep = ReputationService::summary($sellerId);

        return compact('totalProducts', 'totalStock', 'newOrders',
            'revenue30', 'byDay', 'topProducts', 'rep');
    }

    public static function buyerDashboard(int $userId): array
    {
        $orders = (int) Database::scalar(
            "SELECT COUNT(*) FROM orders WHERE user_id = ?", [$userId]
        );
        $delivered = (int) Database::scalar(
            "SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'",
            [$userId]
        );
        $spent = (float) Database::scalar(
            "SELECT COALESCE(SUM(total_amount),0) FROM orders
             WHERE user_id = ? AND status = 'delivered'", [$userId]
        );
        $wishlist = (int) Database::scalar(
            "SELECT COUNT(*) FROM wishlist WHERE user_id = ?", [$userId]
        );

        return compact('orders', 'delivered', 'spent', 'wishlist');
    }
}
