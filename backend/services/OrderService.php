<?php
/**
 * OrderService - end-to-end checkout, status changes and cancellations.
 */

class OrderService
{
    /**
     * Place an order from the current cart.
     * @return array{ok:bool, order_id?:int, message?:string}
     */
    public static function checkout(int $userId, int $addressId, string $paymentMethod = 'simulation'): array
    {
        $items = CartService::items();
        if (empty($items)) {
            return ['ok' => false, 'message' => 'Cart is empty'];
        }

        $address = AddressModel::find($addressId, $userId);
        if (!$address) {
            return ['ok' => false, 'message' => 'Address not found'];
        }

        // Country/tax/shipping
        $country = Database::one(
            "SELECT * FROM countries WHERE country_code = ?",
            [$address['country_code']]
        );
        $taxRate = (float) ($country['tax_rate']      ?? 0);
        $baseShip= (float) ($country['base_shipping'] ?? 0);
        $orderCurrency = $country['currency_code'] ?? 'IDR';

        try {
            return Database::transaction(function () use (
                $userId, $address, $addressId, $paymentMethod,
                $items, $taxRate, $baseShip, $orderCurrency
            ) {
                // Validate stock & convert prices into the order's currency
                $subtotal = 0.0;
                foreach ($items as &$it) {
                    $fresh = ProductModel::find($it['product_id']);
                    if (!$fresh || (int) $fresh['stock'] < $it['qty']) {
                        throw new RuntimeException("Stock insufficient for: " . $it['product_name']);
                    }
                    $it['price_converted'] = Currency::convert(
                        (float) $it['price'], $it['currency_code'], $orderCurrency
                    );
                    $subtotal += $it['price_converted'] * $it['qty'];
                }
                unset($it);

                $shipping = $baseShip;
                $tax      = round($subtotal * $taxRate, 2);
                $total    = round($subtotal + $shipping + $tax, 2);
                $rateUsd  = Currency::rate($orderCurrency);

                $orderId = Database::insert('orders', [
                    'user_id'        => $userId,
                    'address_id'     => $addressId,
                    'status'         => 'pending',
                    'payment_status' => 'unpaid',
                    'payment_method' => $paymentMethod,
                    'subtotal'       => round($subtotal, 2),
                    'shipping_fee'   => round($shipping, 2),
                    'tax_amount'     => $tax,
                    'total_amount'   => $total,
                    'currency_code'  => $orderCurrency,
                    'exchange_rate'  => $rateUsd,
                ]);

                foreach ($items as $it) {
                    Database::insert('order_items', [
                        'order_id'   => $orderId,
                        'product_id' => $it['product_id'],
                        'seller_id'  => $it['seller_id'],
                        'quantity'   => $it['qty'],
                        'price'      => round($it['price_converted'], 2),
                    ]);
                    Database::run(
                        "UPDATE products SET stock = stock - ? WHERE product_id = ?",
                        [$it['qty'], $it['product_id']]
                    );

                    // Notify seller
                    NotificationModel::push(
                        $it['seller_id'],
                        'New order received',
                        "Order #$orderId placed for {$it['product_name']} (x{$it['qty']})",
                        '',
                        '/frontend/pages/seller/orders.php'
                    );
                }

                OrderModel::addTracking($orderId, 'Order placed', 'System',
                    'Awaiting buyer payment.');

                CartService::clear();
                return ['ok' => true, 'order_id' => $orderId];
            });
        } catch (\Throwable $e) {
            logger('Checkout failed: ' . $e->getMessage(), 'error');
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /** Buyer marks order as paid (simulation - escrow holds the money). */
    public static function markPaid(int $orderId, int $buyerId): array
    {
        $order = OrderModel::find($orderId);
        if (!$order || (int) $order['user_id'] !== $buyerId) {
            return ['ok' => false, 'message' => 'Order not found'];
        }
        if ($order['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'Order cannot be paid'];
        }
        OrderModel::setStatus($orderId, 'paid', ['payment_status' => 'held']);
        OrderModel::addTracking($orderId, 'Payment held', 'System',
            'Funds held in escrow simulation.');
        return ['ok' => true];
    }

    /** Seller marks order as shipped. */
    public static function ship(int $orderId, int $sellerId, string $trackingNumber, string $courier): array
    {
        $order = OrderModel::find($orderId);
        if (!$order) return ['ok' => false, 'message' => 'Order not found'];

        // Make sure this seller owns at least one item in the order
        $owns = Database::scalar("
            SELECT COUNT(*) FROM order_items WHERE order_id = ? AND seller_id = ?
        ", [$orderId, $sellerId]);
        if (!$owns) return ['ok' => false, 'message' => 'Not your order'];

        if (!in_array($order['status'], ['paid', 'pending', 'processing'], true)) {
            return ['ok' => false, 'message' => 'Order cannot be shipped'];
        }

        OrderModel::setStatus($orderId, 'shipped', [
            'tracking_number' => $trackingNumber,
            'courier'         => $courier,
            'shipped_at'      => date('Y-m-d H:i:s'),
        ]);
        OrderModel::addTracking($orderId, 'Shipped', $courier, "Tracking: $trackingNumber");
        NotificationModel::push((int) $order['user_id'],
            'Your order is on the way',
            "Order #$orderId shipped via $courier (tracking: $trackingNumber)",
            '',
            '/frontend/pages/buyer/orders.php');

        return ['ok' => true];
    }

    /** Buyer confirms delivery. */
    public static function confirmDelivered(int $orderId, int $buyerId): array
    {
        $order = OrderModel::find($orderId);
        if (!$order || (int) $order['user_id'] !== $buyerId) {
            return ['ok' => false, 'message' => 'Order not found'];
        }
        if ($order['status'] !== 'shipped') {
            return ['ok' => false, 'message' => 'Order not shipped yet'];
        }
        OrderModel::setStatus($orderId, 'delivered', [
            'delivered_at'   => date('Y-m-d H:i:s'),
            'payment_status' => 'released',
        ]);
        OrderModel::addTracking($orderId, 'Delivered', 'Buyer',
            'Buyer confirmed package received.');

        // Recompute reputation for each seller in the order
        $sellerIds = Database::all(
            "SELECT DISTINCT seller_id FROM order_items WHERE order_id = ?",
            [$orderId]
        );
        foreach ($sellerIds as $row) {
            ReputationService::recompute((int) $row['seller_id']);
        }
        return ['ok' => true];
    }

    /**
     * Cancel order using the dynamic cancellation form.
     * @param int   $actorId    user_id of whoever triggers the cancel
     * @param string $actorRole 'buyer' or 'seller'
     * @param array  $form     [reason_id, note, attachment]
     */
    public static function cancel(int $orderId, int $actorId, string $actorRole, array $form): array
    {
        $order = OrderModel::find($orderId);
        if (!$order) return ['ok' => false, 'message' => 'Order not found'];

        if ($actorRole === 'buyer' && (int) $order['user_id'] !== $actorId) {
            return ['ok' => false, 'message' => 'Not your order'];
        }
        if ($actorRole === 'seller') {
            $owns = Database::scalar(
                "SELECT COUNT(*) FROM order_items WHERE order_id = ? AND seller_id = ?",
                [$orderId, $actorId]
            );
            if (!$owns) return ['ok' => false, 'message' => 'Not your order'];
        }

        if (in_array($order['status'], ['delivered', 'cancelled'], true)) {
            return ['ok' => false, 'message' => 'Order already finalized'];
        }

        // Validate against the dynamic reason list
        $reason = !empty($form['reason_id'])
            ? CancellationModel::findReason((int) $form['reason_id'])
            : null;
        if ($reason && $reason['audience'] !== $actorRole) {
            return ['ok' => false, 'message' => 'Invalid reason for your role'];
        }
        if ($reason && (int) $reason['requires_note'] === 1 && empty(trim($form['note'] ?? ''))) {
            return ['ok' => false, 'message' => 'A note is required for this reason'];
        }

        Database::transaction(function () use ($orderId, $actorId, $actorRole, $form, $reason, $order) {
            CancellationModel::create([
                'order_id'      => $orderId,
                'cancelled_by'  => $actorRole,
                'actor_user_id' => $actorId,
                'reason_id'     => $reason['reason_id'] ?? null,
                'reason_text'   => $reason['label']     ?? ($form['reason_text'] ?? null),
                'note'          => $form['note']        ?? null,
                'attachment'    => $form['attachment']  ?? null,
            ]);

            OrderModel::setStatus($orderId, 'cancelled', [
                'cancelled_at'   => date('Y-m-d H:i:s'),
                'payment_status' => $order['payment_status'] === 'held' ? 'refunded' : 'unpaid',
            ]);
            OrderModel::addTracking($orderId, 'Cancelled by ' . $actorRole, 'System',
                $reason['label'] ?? ($form['reason_text'] ?? 'Cancelled'));

            // Restock
            $items = Database::all(
                "SELECT product_id, quantity FROM order_items WHERE order_id = ?",
                [$orderId]
            );
            foreach ($items as $it) {
                Database::run(
                    "UPDATE products SET stock = stock + ? WHERE product_id = ?",
                    [$it['quantity'], $it['product_id']]
                );
            }

            // Notify the other party
            if ($actorRole === 'buyer') {
                $sellerIds = Database::all(
                    "SELECT DISTINCT seller_id FROM order_items WHERE order_id = ?",
                    [$orderId]
                );
                foreach ($sellerIds as $row) {
                    NotificationModel::push((int) $row['seller_id'],
                        'Order cancelled by buyer',
                        "Order #$orderId was cancelled. Reason: " .
                            ($reason['label'] ?? '—'),
                        '', '/frontend/pages/seller/orders.php');
                }
            } else {
                NotificationModel::push((int) $order['user_id'],
                    'Order cancelled by seller',
                    "Order #$orderId was cancelled. Reason: " .
                        ($reason['label'] ?? '—'),
                    '', '/frontend/pages/buyer/orders.php');
            }
        });

        return ['ok' => true];
    }
}
