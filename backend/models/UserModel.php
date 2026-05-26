<?php
/**
 * UserModel - account-level operations.
 *
 * The original project had two separate tables (`users` and `sellers`).
 * The new schema uses a unified `users` table with a `role` column and
 * 1:1 profile tables.  This model wraps the new schema while keeping
 * the API simple for the rest of the codebase.
 */

class UserModel
{
    /* ---------------------------------------------------------------
     | Lookup
     |---------------------------------------------------------------*/

    public static function findById(int $id): ?array
    {
        return Database::one(
            "SELECT * FROM users WHERE user_id = ?",
            [$id]
        );
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::one(
            "SELECT * FROM users WHERE email = ? LIMIT 1",
            [$email]
        );
    }

    public static function emailExists(string $email): bool
    {
        return Database::scalar(
            "SELECT COUNT(*) FROM users WHERE email = ?",
            [$email]
        ) > 0;
    }

    /* ---------------------------------------------------------------
     | Mutations
     |---------------------------------------------------------------*/

    /**
     * Create a new account.  Also creates the matching profile row
     * inside a transaction so we never end up with an orphan.
     */
    public static function create(array $data): int
    {
        return Database::transaction(function () use ($data) {
            $userId = Database::insert('users', [
                'name'              => $data['name'],
                'email'             => $data['email'],
                'password'          => password_hash($data['password'], PASSWORD_DEFAULT),
                'role'              => $data['role'] ?? 'buyer',
                'country_code'      => $data['country_code'] ?? 'ID',
                'preferred_currency'=> $data['preferred_currency'] ?? 'IDR',
            ]);

            if (($data['role'] ?? 'buyer') === 'seller') {
                Database::insert('seller_profiles', [
                    'user_id'   => $userId,
                    'shop_name' => $data['shop_name'] ?? $data['name'],
                ]);
            } else {
                Database::insert('buyer_profiles', ['user_id' => $userId]);
            }

            return $userId;
        });
    }

    public static function touchLogin(int $userId): void
    {
        Database::run(
            "UPDATE users SET last_login_at = NOW() WHERE user_id = ?",
            [$userId]
        );
    }

    public static function updateProfile(int $userId, array $data): void
    {
        $allowed = ['name', 'phone', 'avatar', 'country_code', 'preferred_currency'];
        $update  = array_intersect_key($data, array_flip($allowed));
        if (!empty($update)) {
            Database::update('users', $update, 'user_id = ?', [$userId]);
        }
    }

    /* ---------------------------------------------------------------
     | Profiles
     |---------------------------------------------------------------*/

    /** Combined user + seller_profiles row. */
    public static function sellerProfile(int $userId): ?array
    {
        return Database::one("
            SELECT u.user_id, u.name, u.email, u.avatar, u.country_code,
                   u.preferred_currency, u.created_at, u.last_login_at,
                   sp.shop_name, sp.description, sp.banner,
                   sp.avg_rating, sp.total_reviews, sp.total_sales,
                   sp.total_cancellations, sp.cancellation_rate,
                   sp.delivery_success_rate, sp.response_time_min, sp.is_verified
            FROM users u
            JOIN seller_profiles sp ON sp.user_id = u.user_id
            WHERE u.user_id = ? AND u.role = 'seller'
        ", [$userId]);
    }

    public static function buyerProfile(int $userId): ?array
    {
        return Database::one("
            SELECT u.user_id, u.name, u.email, u.avatar, u.country_code,
                   u.preferred_currency, u.created_at,
                   bp.bio, bp.total_orders, bp.total_spent
            FROM users u
            JOIN buyer_profiles bp ON bp.user_id = u.user_id
            WHERE u.user_id = ? AND u.role = 'buyer'
        ", [$userId]);
    }

    public static function updateSellerProfile(int $userId, array $data): void
    {
        $allowed = ['shop_name', 'description', 'banner'];
        $update  = array_intersect_key($data, array_flip($allowed));
        if (!empty($update)) {
            Database::update('seller_profiles', $update, 'user_id = ?', [$userId]);
        }
    }

    public static function badges(int $userId): array
    {
        return Database::all("
            SELECT b.*
            FROM seller_badges sb
            JOIN badges b ON b.badge_id = sb.badge_id
            WHERE sb.user_id = ?
        ", [$userId]);
    }
}
