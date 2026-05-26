<?php
/**
 * CartService - session-based shopping cart.
 *
 * Cart shape:  $_SESSION['cart'] = [ product_id => ['qty' => N], ... ]
 * Snapshots of price & name are looked up on demand to keep the
 * session payload tiny and always consistent with the products table.
 */

class CartService
{
    public static function add(int $productId, int $qty = 1): array
    {
        $product = ProductModel::find($productId);
        if (!$product) return ['ok' => false, 'message' => 'Product not found'];
        if ((int) $product['stock'] < 1) return ['ok' => false, 'message' => 'Out of stock'];

        $_SESSION['cart'] = $_SESSION['cart'] ?? [];
        $current = $_SESSION['cart'][$productId]['qty'] ?? 0;
        $newQty  = min($current + $qty, (int) $product['stock']);
        $_SESSION['cart'][$productId] = ['qty' => $newQty];

        return ['ok' => true, 'qty' => $newQty];
    }

    public static function update(array $quantities): void
    {
        foreach ($quantities as $pid => $qty) {
            $pid = (int) $pid;
            $qty = (int) $qty;
            if ($qty <= 0) {
                unset($_SESSION['cart'][$pid]);
                continue;
            }
            $product = ProductModel::find($pid);
            if (!$product) continue;
            $_SESSION['cart'][$pid] = ['qty' => min($qty, (int) $product['stock'])];
        }
    }

    public static function remove(int $productId): void
    {
        unset($_SESSION['cart'][$productId]);
    }

    public static function clear(): void
    {
        unset($_SESSION['cart']);
    }

    /** Hydrated cart items joined with current product data. */
    public static function items(): array
    {
        $items = [];
        foreach ($_SESSION['cart'] ?? [] as $pid => $entry) {
            $p = ProductModel::find((int) $pid);
            if (!$p) continue;
            $qty   = (int) $entry['qty'];
            $items[] = [
                'product_id'    => (int) $p['product_id'],
                'product_name'  => $p['product_name'],
                'price'         => (float) $p['price'],
                'currency_code' => $p['currency_code'],
                'image'         => $p['image'],
                'stock'         => (int) $p['stock'],
                'seller_id'     => (int) $p['seller_id'],
                'seller_name'   => $p['seller_name'],
                'shop_name'     => $p['shop_name'],
                'qty'           => $qty,
                'subtotal'      => $qty * (float) $p['price'],
            ];
        }
        return $items;
    }

    public static function totals(?string $displayCurrency = null): array
    {
        $items    = self::items();
        $count    = 0;
        $subtotal = 0.0;
        $currency = null;

        foreach ($items as $it) {
            $count    += $it['qty'];
            $subtotal += $it['subtotal'];
            $currency = $currency ?? $it['currency_code'];
        }
        $currency = $currency ?? config('defaults.currency');

        if ($displayCurrency && $displayCurrency !== $currency) {
            $subtotal = Currency::convert($subtotal, $currency, $displayCurrency);
            $currency = $displayCurrency;
        }
        return [
            'count'    => $count,
            'subtotal' => round($subtotal, 2),
            'currency' => $currency,
        ];
    }

    public static function count(): int
    {
        $n = 0;
        foreach ($_SESSION['cart'] ?? [] as $entry) {
            $n += (int) $entry['qty'];
        }
        return $n;
    }
}
