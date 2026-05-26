<?php
/**
 * AddressModel - user shipping addresses.
 */

class AddressModel
{
    public static function listFor(int $userId): array
    {
        return Database::all("
            SELECT a.*, c.country_name
            FROM addresses a
            JOIN countries c ON a.country_code = c.country_code
            WHERE a.user_id = ?
            ORDER BY a.is_default DESC, a.created_at DESC
        ", [$userId]);
    }

    public static function defaultFor(int $userId): ?array
    {
        return Database::one("
            SELECT a.*, c.country_name
            FROM addresses a
            JOIN countries c ON a.country_code = c.country_code
            WHERE a.user_id = ?
            ORDER BY a.is_default DESC, a.created_at DESC
            LIMIT 1
        ", [$userId]);
    }

    public static function find(int $addressId, int $userId): ?array
    {
        return Database::one(
            "SELECT * FROM addresses WHERE address_id = ? AND user_id = ?",
            [$addressId, $userId]
        );
    }

    public static function create(int $userId, array $data): int
    {
        $isDefault = !empty($data['is_default']) ? 1 : 0;
        if ($isDefault) {
            Database::run("UPDATE addresses SET is_default = 0 WHERE user_id = ?", [$userId]);
        }
        return Database::insert('addresses', [
            'user_id'      => $userId,
            'label'        => $data['label']     ?? 'Home',
            'recipient'    => $data['recipient'],
            'phone'        => $data['phone'],
            'line1'        => $data['line1'],
            'line2'        => $data['line2']     ?? null,
            'city'         => $data['city'],
            'state'        => $data['state']     ?? null,
            'postal_code'  => $data['postal_code'] ?? null,
            'country_code' => $data['country_code'],
            'latitude'     => $data['latitude']  ?? null,
            'longitude'    => $data['longitude'] ?? null,
            'is_default'   => $isDefault,
        ]);
    }
}
