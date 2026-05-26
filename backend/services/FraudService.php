<?php
/**
 * FraudService - very simple heuristics that produce a "fraud score"
 * and feed the admin moderation tools.  Not security-grade!
 */

class FraudService
{
    /** Compute a 0..100 risk score for an order. */
    public static function scoreOrder(int $orderId): array
    {
        $order = OrderModel::find($orderId);
        if (!$order) return ['score' => 0, 'reasons' => []];

        $reasons = [];
        $score   = 0;

        // 1. Brand-new buyer placing big order
        $age = (int) Database::scalar("
            SELECT TIMESTAMPDIFF(DAY, created_at, NOW()) FROM users WHERE user_id = ?
        ", [$order['user_id']]);
        if ($age < 1)               { $score += 30; $reasons[] = 'New account (<1 day)'; }
        elseif ($age < 7)           { $score += 15; $reasons[] = 'Account younger than a week'; }

        if ($order['total_amount'] > 5_000_000) {
            $score += 15;
            $reasons[] = 'High value order';
        }

        // 2. Many recent cancellations from this buyer
        $cancels = (int) Database::scalar("
            SELECT COUNT(*) FROM orders
            WHERE user_id = ? AND status = 'cancelled'
              AND order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", [$order['user_id']]);
        if ($cancels >= 3) { $score += 25; $reasons[] = "Buyer cancelled $cancels orders this month"; }

        // 3. Address country differs from account country
        $addr = Database::one("SELECT country_code FROM addresses WHERE address_id = ?",
            [$order['address_id']]);
        if ($addr && $addr['country_code'] !== ($order['country_code'] ?? 'ID')) {
            // (`country_code` field on order isn't stored, but the buyer has one)
        }

        $score = min($score, 100);

        if ($score >= 40) {
            Database::insert('fraud_flags', [
                'user_id'  => $order['user_id'],
                'order_id' => $orderId,
                'score'    => $score,
                'reason'   => implode('; ', $reasons),
            ]);
        }
        return ['score' => $score, 'reasons' => $reasons];
    }
}
