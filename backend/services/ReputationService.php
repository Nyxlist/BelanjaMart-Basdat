<?php
/**
 * ReputationService - everything related to seller reputation.
 *
 * The schema's triggers handle the heavy lifting (rating averages,
 * cancellation counts).  This service exposes higher-level reads
 * (badges, summary cards) and recomputes derived metrics that don't
 * have a trigger.
 */

class ReputationService
{
    public const BADGE_TRUSTED = 'trusted_seller';
    public const BADGE_TOP     = 'top_rated';
    public const BADGE_FAST    = 'fast_response';
    public const BADGE_VERIFY  = 'verified';

    /**
     * Rebuild derived stats and (un)award badges accordingly.
     * Call after a review, completed sale or chat reply.
     */
    public static function recompute(int $sellerId): void
    {
        // Average response time (minutes) - first message reply by seller
        $avgResp = (int) Database::scalar("
            SELECT AVG(TIMESTAMPDIFF(MINUTE, c.created_at, m.created_at))
            FROM chats c
            JOIN chat_messages m ON m.chat_id = c.chat_id
            WHERE c.seller_id = ? AND m.sender_id = c.seller_id
        ", [$sellerId]) ?: 0;

        // Cancellation rate
        $stats = Database::one("
            SELECT total_sales, total_cancellations FROM seller_profiles WHERE user_id = ?
        ", [$sellerId]);
        $sales  = (int) ($stats['total_sales'] ?? 0);
        $cancel = (int) ($stats['total_cancellations'] ?? 0);
        $denom  = max($sales + $cancel, 1);
        $rate   = round($cancel / $denom * 100, 2);
        $delivOk = round(100 - $rate, 2);

        Database::update('seller_profiles', [
            'response_time_min'     => $avgResp,
            'cancellation_rate'     => $rate,
            'delivery_success_rate' => $delivOk,
        ], 'user_id = ?', [$sellerId]);

        self::awardBadges($sellerId);
    }

    /** Auto-award/remove badges based on current stats. */
    public static function awardBadges(int $sellerId): void
    {
        $sp = Database::one("SELECT * FROM seller_profiles WHERE user_id = ?", [$sellerId]);
        if (!$sp) return;

        $badges = self::badgesByCode();
        $rules  = [
            self::BADGE_TRUSTED => $sp['avg_rating'] >= 4.5 && $sp['total_reviews'] >= 50,
            self::BADGE_TOP     => $sp['avg_rating'] >= 4.8 && $sp['total_reviews'] >= 100,
            self::BADGE_FAST    => $sp['response_time_min'] > 0 && $sp['response_time_min'] < 30,
            self::BADGE_VERIFY  => (int) $sp['is_verified'] === 1,
        ];

        foreach ($rules as $code => $eligible) {
            if (!isset($badges[$code])) continue;
            $badgeId = (int) $badges[$code]['badge_id'];
            if ($eligible) {
                Database::run("
                    INSERT IGNORE INTO seller_badges (user_id, badge_id) VALUES (?, ?)
                ", [$sellerId, $badgeId]);
            } else {
                Database::delete('seller_badges',
                    'user_id = ? AND badge_id = ?', [$sellerId, $badgeId]);
            }
        }
    }

    /** Compact human-readable summary used by seller cards. */
    public static function summary(int $sellerId): array
    {
        $sp = Database::one("SELECT * FROM seller_profiles WHERE user_id = ?", [$sellerId]);
        if (!$sp) return [];

        return [
            'avg_rating'        => (float) $sp['avg_rating'],
            'total_reviews'     => (int)   $sp['total_reviews'],
            'total_sales'       => (int)   $sp['total_sales'],
            'cancellation_rate' => (float) $sp['cancellation_rate'],
            'delivery_success'  => (float) $sp['delivery_success_rate'],
            'response_time'     => (int)   $sp['response_time_min'],
            'verified'          => (bool)  $sp['is_verified'],
        ];
    }

    private static function badgesByCode(): array
    {
        $out = [];
        foreach (Database::all("SELECT * FROM badges") as $b) {
            $out[$b['code']] = $b;
        }
        return $out;
    }
}
