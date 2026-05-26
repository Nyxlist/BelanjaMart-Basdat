<?php
/**
 * CancellationModel - dynamic cancellation forms.
 */

class CancellationModel
{
    /** Reasons offered to a given audience ("buyer" or "seller"). */
    public static function reasonsFor(string $audience): array
    {
        return Database::all("
            SELECT * FROM cancellation_reasons
            WHERE audience = ? AND is_active = 1
            ORDER BY sort_order, label
        ", [$audience]);
    }

    public static function findReason(int $reasonId): ?array
    {
        return Database::one("SELECT * FROM cancellation_reasons WHERE reason_id = ?", [$reasonId]);
    }

    public static function create(array $data): int
    {
        return Database::insert('cancellations', [
            'order_id'      => (int) $data['order_id'],
            'cancelled_by'  => $data['cancelled_by'],
            'actor_user_id' => (int) $data['actor_user_id'],
            'reason_id'     => $data['reason_id'] ?? null,
            'reason_text'   => $data['reason_text'] ?? null,
            'note'          => $data['note'] ?? null,
            'attachment'    => $data['attachment'] ?? null,
        ]);
    }

    public static function forOrder(int $orderId): ?array
    {
        return Database::one(
            "SELECT * FROM cancellations WHERE order_id = ? ORDER BY created_at DESC LIMIT 1",
            [$orderId]
        );
    }

    /* ---------- Reason management (admin / seller-customizable) ---------- */

    public static function addReason(string $audience, string $label, bool $requiresNote = false): int
    {
        return Database::insert('cancellation_reasons', [
            'audience'      => $audience,
            'label'         => $label,
            'requires_note' => $requiresNote ? 1 : 0,
        ]);
    }

    public static function setReasonActive(int $reasonId, bool $active): void
    {
        Database::update('cancellation_reasons', ['is_active' => $active ? 1 : 0],
            'reason_id = ?', [$reasonId]);
    }
}
