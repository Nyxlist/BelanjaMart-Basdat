<?php
/**
 * ProductModel - everything related to product listings, including
 * filtering, search and basic CRUD for sellers.
 */

class ProductModel
{
    /** Return products that match the given filters. */
    public static function search(array $filters = []): array
    {
        $where  = ['p.is_active = 1'];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[]  = 'p.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (!empty($filters['seller_id'])) {
            $where[]  = 'p.seller_id = ?';
            $params[] = (int) $filters['seller_id'];
        }
        if (!empty($filters['search'])) {
            $where[]  = '(p.product_name LIKE ? OR p.description LIKE ?)';
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['min_rating'])) {
            $where[]  = 'p.average_rating >= ?';
            $params[] = (float) $filters['min_rating'];
        }
        if (!empty($filters['in_stock'])) {
            $where[] = 'p.stock > 0';
        }

        $orderBy = match ($filters['sort'] ?? '') {
            'price_asc'  => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'rating'     => 'p.average_rating DESC, p.total_reviews DESC',
            'popular'    => 'p.total_sold DESC',
            default      => 'p.created_at DESC',
        };

        $sql = "SELECT p.*, c.category_name, c.icon AS category_icon,
                       u.name AS seller_name, sp.shop_name,
                       sp.avg_rating AS seller_rating, sp.is_verified AS seller_verified
                FROM products p
                LEFT JOIN categories c     ON p.category_id = c.category_id
                LEFT JOIN users u          ON p.seller_id   = u.user_id
                LEFT JOIN seller_profiles sp ON sp.user_id  = u.user_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY $orderBy";

        // basic pagination
        $limit  = (int) ($filters['limit']  ?? 24);
        $offset = (int) ($filters['offset'] ?? 0);
        $sql   .= " LIMIT $limit OFFSET $offset";

        return Database::all($sql, $params);
    }

    public static function find(int $id): ?array
    {
        return Database::one("
            SELECT p.*, c.category_name, c.icon AS category_icon,
                   u.name AS seller_name, sp.shop_name,
                   sp.avg_rating AS seller_rating,
                   sp.total_reviews AS seller_total_reviews,
                   sp.is_verified AS seller_verified,
                   sp.cancellation_rate, sp.response_time_min
            FROM products p
            LEFT JOIN categories c       ON p.category_id = c.category_id
            LEFT JOIN users u            ON p.seller_id   = u.user_id
            LEFT JOIN seller_profiles sp ON sp.user_id    = u.user_id
            WHERE p.product_id = ?
        ", [$id]);
    }

    public static function bySeller(int $sellerId): array
    {
        return Database::all("
            SELECT p.*, c.category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.seller_id = ?
            ORDER BY p.created_at DESC
        ", [$sellerId]);
    }

    public static function categories(): array
    {
        return Database::all("SELECT * FROM categories ORDER BY category_name");
    }

    public static function create(int $sellerId, array $data): int
    {
        return Database::insert('products', [
            'seller_id'    => $sellerId,
            'category_id'  => $data['category_id'] ?? null,
            'product_name' => $data['product_name'],
            'description'  => $data['description'] ?? '',
            'price'        => (float) $data['price'],
            'currency_code'=> $data['currency_code'] ?? 'IDR',
            'stock'        => (int) $data['stock'],
            'weight_grams' => (int) ($data['weight_grams'] ?? 500),
            'image'        => $data['image'] ?? null,
        ]);
    }

    public static function update(int $productId, int $sellerId, array $data): bool
    {
        $allowed = ['product_name', 'description', 'price', 'stock',
                    'category_id', 'currency_code', 'weight_grams', 'image', 'is_active'];
        $update  = array_intersect_key($data, array_flip($allowed));
        if (empty($update)) return false;

        $rows = Database::update(
            'products', $update,
            'product_id = ? AND seller_id = ?',
            [$productId, $sellerId]
        );
        return $rows > 0;
    }

    public static function delete(int $productId, int $sellerId): bool
    {
        return Database::delete(
            'products',
            'product_id = ? AND seller_id = ?',
            [$productId, $sellerId]
        ) > 0;
    }

    public static function incrementViews(int $productId): void
    {
        Database::run(
            "UPDATE products SET views = views + 1 WHERE product_id = ?",
            [$productId]
        );
    }
}
