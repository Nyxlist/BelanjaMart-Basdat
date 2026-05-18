<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . "/../config/config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer') {
    header("Location: ../auth/role.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Ambil semua pesanan user ini
$orders = mysqli_query($conn, "
    SELECT o.order_id, o.order_date, o.status, o.tracking_number,
           o.delivered_at
    FROM orders o
    WHERE o.user_id = $user_id
    ORDER BY o.order_date DESC
");

$status_label = [
    'pending'   => ['label' => 'Menunggu Konfirmasi', 'color' => '#e67e22', 'bg' => '#fef3e2'],
    'shipped'   => ['label' => 'Sedang Dikirim',      'color' => '#2980b9', 'bg' => '#eaf4fb'],
    'delivered' => ['label' => 'Sudah Diterima',      'color' => '#27ae60', 'bg' => '#e8f8f0'],
    'cancelled' => ['label' => 'Dibatalkan',           'color' => '#e74c3c', 'bg' => '#fdecea'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya – BelanjaMart</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; color: #333; }

        .topbar {
            background: #c0392b; color: white;
            padding: 0 24px; display: flex;
            align-items: center; justify-content: space-between;
            height: 56px; box-shadow: 0 2px 8px rgba(0,0,0,.2);
        }
        .topbar .logo { font-size: 22px; font-weight: bold; }
        .topbar .logo span { color: #f9ca24; }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .topbar-right a { color: white; text-decoration: none; font-size: 14px; }

        .wrap { max-width: 900px; margin: 30px auto; padding: 0 16px; }
        .page-title { font-size: 22px; font-weight: bold; margin-bottom: 20px; }

        .order-card {
            background: white; border-radius: 10px;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            margin-bottom: 16px; overflow: hidden;
        }
        .order-header {
            padding: 14px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
        }
        .order-header-left { display: flex; align-items: center; gap: 16px; }
        .order-id { font-weight: bold; font-size: 15px; }
        .order-date { font-size: 13px; color: #888; }
        .order-tracking { font-size: 12px; color: #555; }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .order-items { padding: 0 20px; }
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #f5f5f5;
            gap: 12px;
        }
        .order-item:last-child { border-bottom: none; }
        .item-info .name { font-weight: 600; font-size: 14px; }
        .item-info .meta { font-size: 12px; color: #888; margin-top: 3px; }
        .item-price { font-weight: bold; color: #c0392b; white-space: nowrap; font-size: 14px; }
        .item-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        .btn-review {
            background: #27ae60; color: white;
            border: none; padding: 7px 14px;
            border-radius: 6px; cursor: pointer;
            font-size: 13px; font-weight: 500;
            text-decoration: none; display: inline-block;
        }
        .btn-review:hover { background: #219a52; }
        .reviewed-tag {
            background: #eafaf1; color: #27ae60;
            border: 1px solid #a9dfbf;
            padding: 5px 12px; border-radius: 6px;
            font-size: 13px; font-weight: 500;
        }

        .empty-state { text-align: center; padding: 60px 20px; color: #aaa; background: white; border-radius: 10px; }
        .empty-state .icon { font-size: 64px; margin-bottom: 12px; }
        .btn-shop { display: inline-block; background: #c0392b; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 12px; }

        .flash { padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .flash-ok  { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>

<div class="topbar">
    <div class="logo">Belanja<span>Mart</span></div>
    <div class="topbar-right">
        <a href="index.php">🏠 Belanja</a>
        <a href="cart.php">🛒 Keranjang</a>
        <a href="../auth/logout.php">Keluar</a>
    </div>
</div>

<div class="wrap">
    <div class="page-title">📦 Pesanan Saya</div>

    <?php if (isset($_SESSION['flash_ok'])): ?>
        <div class="flash flash-ok">✅ <?= $_SESSION['flash_ok'] ?></div>
        <?php unset($_SESSION['flash_ok']); ?>
    <?php endif; ?>

    <?php if (!$orders || mysqli_num_rows($orders) == 0): ?>
        <div class="empty-state">
            <div class="icon">📦</div>
            <p>Kamu belum punya pesanan.</p>
            <a href="index.php" class="btn-shop">Mulai Belanja</a>
        </div>
    <?php else: ?>

    <?php while ($order = mysqli_fetch_assoc($orders)):
        $st = $status_label[$order['status']] ?? ['label' => $order['status'], 'color' => '#888', 'bg' => '#eee'];

        // Ambil item-item dalam order ini
        $items = mysqli_query($conn, "
            SELECT oi.order_item_id, oi.quantity, oi.price,
                   p.product_name, s.name AS seller_name,
                   (oi.quantity * oi.price) AS subtotal,
                   (SELECT COUNT(*) FROM reviews r WHERE r.order_item_id = oi.order_item_id) AS has_review
            FROM order_items oi
            JOIN products p ON oi.product_id = p.product_id
            JOIN sellers s ON p.seller_id = s.seller_id
            WHERE oi.order_id = {$order['order_id']}
        ");

        $order_total = 0;
        $items_arr   = [];
        while ($it = mysqli_fetch_assoc($items)) {
            $order_total += $it['subtotal'];
            $items_arr[]  = $it;
        }
    ?>
    <div class="order-card">
        <div class="order-header">
            <div class="order-header-left">
                <span class="order-id">Order #<?= $order['order_id'] ?></span>
                <span class="order-date">📅 <?= date('d M Y, H:i', strtotime($order['order_date'])) ?></span>
                <?php if ($order['tracking_number']): ?>
                    <span class="order-tracking">🚚 Resi: <?= htmlspecialchars($order['tracking_number']) ?></span>
                <?php endif; ?>
            </div>
            <span class="status-badge" style="color:<?= $st['color'] ?>; background:<?= $st['bg'] ?>;">
                <?= $st['label'] ?>
            </span>
        </div>

        <div class="order-items">
            <?php foreach ($items_arr as $it): ?>
            <div class="order-item">
                <div class="item-info" style="flex:1;">
                    <div class="name"><?= htmlspecialchars($it['product_name']) ?></div>
                    <div class="meta">Seller: <?= htmlspecialchars($it['seller_name']) ?> &nbsp;|&nbsp; <?= $it['quantity'] ?> × Rp <?= number_format($it['price'], 0, ',', '.') ?></div>
                </div>
                <div class="item-price">Rp <?= number_format($it['subtotal'], 0, ',', '.') ?></div>
                <div class="item-actions">
                    <?php if ($order['status'] === 'delivered'): ?>
                        <?php if ($it['has_review'] > 0): ?>
                            <span class="reviewed-tag">✅ Sudah Diulas</span>
                        <?php else: ?>
                            <a href="review.php?order_item_id=<?= $it['order_item_id'] ?>" class="btn-review">
                                ⭐ Tulis Ulasan
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="padding: 12px 20px; background: #f8f9fa; text-align: right; font-weight: bold; font-size: 15px; color: #c0392b; border-top: 1px solid #eee;">
            Total: Rp <?= number_format($order_total, 0, ',', '.') ?>
        </div>
    </div>
    <?php endwhile; ?>

    <?php endif; ?>
</div>

</body>
</html>