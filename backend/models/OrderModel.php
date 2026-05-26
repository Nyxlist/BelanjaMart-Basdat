<?php
/**
 * OrderModel - order CRUD and status transitions.
 */

class OrderModel
{
    public const STATUSES = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled'];

    public static function find(int $orderId): ?array
    {
        return Database::one("
            SELECT o.*, u.name AS buyer_name, a.recipient, a.line1, a.line2,
                   a.city, a.state, a.postal_code, a.country_code
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            LEFT JOIN addresses a ON a.address_id = o.address_id
            WHERE o.order_id = ?
        ", [$orderId]);
    }

    /** Items inside an order (with product + seller info). */
    public static function items(int $orderId): array
    {
        return Database::all("
            SELECT oi.*, p.product_name, p.image, sp.shop_name,
                   u.name AS seller_name,
                   (oi.quantity * oi.price) AS subtotal,
                   (SELECT COUNT(*) FROM reviews r WHERE r.order_item_id = oi.order_item_id) AS has_review
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            JOIN users u    ON oi.seller_id  = u.user_id
            LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
            WHERE oi.order_id = ?
        ", [$orderId]);
    }

    public static function forBuyer(int $userId): array
    {
        return Database::all("
            SELECT o.*,
                   (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count
            FROM orders o
            WHERE o.user_id = ?
            ORDER BY o.order_date DESC
        ", [$userId]);
    }

    public static function forSeller(int $sellerId): array
    {
        return Database::all("
            SELECT o.order_id, o.order_date, o.status, o.tracking_number, o.payment_status,
                   o.currency_code, u.name AS buyer_name,
                   GROUP_CONCAT(p.product_name SEPARATOR ', ') AS product_names,
                   SUM(oi.quantity * oi.price) AS subtotal
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            JOIN products p     ON oi.product_id = p.product_id
            JOIN users u        ON o.user_id = u.user_id
            WHERE oi.seller_id = ?
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
        ", [$sellerId]);
    }

    public static function setStatus(int $orderId, string $status, array $extra = []): void
    {
        $allowed = ['tracking_number', 'courier', 'shipped_at', 'delivered_at', 'cancelled_at',
                    'payment_status', 'notes'];
        $update  = array_intersect_key($extra, array_flip($allowed));
        $update['status'] = $status;
        Database::update('orders', $update, 'order_id = ?', [$orderId]);
    }

    public static function tracking(int $orderId): array
    {
        return Database::all(
            "SELECT * FROM order_tracking WHERE order_id = ? ORDER BY happened_at ASC",
            [$orderId]
        );
    }

    public static function addTracking(int $orderId, string $label, ?string $location = null, ?string $note = null): int
    {
        return Database::insert('order_tracking', [
            'order_id'    => $orderId,
            'event_label' => $label,
            'location'    => $location,
            'note'        => $note,
        ]);
    }
}
