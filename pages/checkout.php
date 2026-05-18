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
$cart    = $_SESSION['cart'] ?? [];

if (empty($cart)) {
    header("Location: cart.php");
    exit;
}

$error = '';

// ── PROSES CHECKOUT ──
if (isset($_POST['checkout'])) {

    // Validasi stok sebelum insert
    foreach ($cart as $pid => $item) {
        $pid = (int) $pid;
        $qty = (int) $item['qty'];
        $stok = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stock FROM products WHERE product_id = $pid"));
        if (!$stok || $stok['stock'] < $qty) {
            $error = "Stok produk \"" . htmlspecialchars($item['product_name']) . "\" tidak mencukupi.";
            break;
        }
    }

    if (!$error) {
        // Buat order baru (status: pending)
        mysqli_query($conn, "
            INSERT INTO orders (user_id, status)
            VALUES ($user_id, 'pending')
        ");
        $order_id = mysqli_insert_id($conn);

        // Insert order items & kurangi stok
        foreach ($cart as $pid => $item) {
            $pid   = (int) $pid;
            $qty   = (int) $item['qty'];
            $price = (float) $item['price'];

            mysqli_query($conn, "
                INSERT INTO order_items (order_id, product_id, quantity, price)
                VALUES ($order_id, $pid, $qty, $price)
            ");

            mysqli_query($conn, "
                UPDATE products SET stock = stock - $qty WHERE product_id = $pid
            ");
        }

        // Kosongkan keranjang
        unset($_SESSION['cart']);
        $_SESSION['flash_ok'] = "Pesanan berhasil dibuat! No. Order #$order_id";
        header("Location: orders.php");
        exit;
    }
}

// Hitung total + enrich data
$total = 0;
$items_display = [];
foreach ($cart as $pid => $item) {
    $pid = (int) $pid;
    $sp  = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT p.product_name, p.price, p.stock, s.name AS seller_name
        FROM products p
        JOIN sellers s ON p.seller_id = s.seller_id
        WHERE p.product_id = $pid
    "));
    if ($sp) {
        $subtotal = $sp['price'] * $item['qty'];
        $total   += $subtotal;
        $items_display[] = [
            'product_name' => $sp['product_name'],
            'seller_name'  => $sp['seller_name'],
            'price'        => $sp['price'],
            'qty'          => $item['qty'],
            'subtotal'     => $subtotal,
            'stock'        => $sp['stock'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout – BelanjaMart</title>
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

        .wrap { max-width: 800px; margin: 30px auto; padding: 0 16px; display: grid; grid-template-columns: 1fr 340px; gap: 20px; }
        .page-title { font-size: 22px; font-weight: bold; margin-bottom: 20px; grid-column: 1/-1; }

        .card { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 20px; }
        .card h3 { font-size: 16px; font-weight: bold; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }

        .order-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid #f5f5f5; }
        .order-item:last-child { border-bottom: none; }
        .item-left .name { font-weight: 600; font-size: 14px; }
        .item-left .seller { font-size: 12px; color: #888; margin-top: 2px; }
        .item-left .qty-price { font-size: 13px; color: #aaa; margin-top: 4px; }
        .item-right { font-weight: bold; color: #c0392b; text-align: right; white-space: nowrap; }

        .summary-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; border-bottom: 1px solid #f5f5f5; }
        .summary-row:last-of-type { border-bottom: none; }
        .summary-total { font-size: 20px; font-weight: bold; color: #c0392b; }

        .info-section { margin-bottom: 16px; }
        .info-section label { display: block; font-size: 13px; color: #666; margin-bottom: 6px; font-weight: 500; }
        .info-box { background: #f8f9fa; padding: 12px; border-radius: 8px; font-size: 14px; }

        .btn-checkout {
            display: block; width: 100%; background: #c0392b; color: white;
            border: none; padding: 14px; border-radius: 8px; cursor: pointer;
            font-size: 16px; font-weight: bold; text-align: center; margin-top: 16px;
            transition: background .15s;
        }
        .btn-checkout:hover { background: #a93226; }
        .btn-back { color: #c0392b; text-decoration: none; font-size: 14px; display: inline-block; margin-bottom: 8px; grid-column: 1/-1; }
        .btn-back:hover { text-decoration: underline; }

        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; grid-column: 1/-1; }

        .steps { display: flex; gap: 0; margin-bottom: 0; grid-column: 1/-1; }
        .step { flex: 1; text-align: center; padding: 10px; font-size: 13px; background: #f0f0f0; color: #aaa; }
        .step.active { background: #c0392b; color: white; font-weight: bold; }
        .step:first-child { border-radius: 8px 0 0 8px; }
        .step:last-child { border-radius: 0 8px 8px 0; }
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
    <div class="steps">
        <div class="step">🛒 Keranjang</div>
        <div class="step active">✅ Konfirmasi</div>
        <div class="step">📦 Pesanan</div>
    </div>

    <a href="cart.php" class="btn-back">← Kembali ke Keranjang</a>

    <?php if ($error): ?>
        <div class="error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- KIRI: Ringkasan Produk -->
    <div>
        <div class="card">
            <h3>📦 Produk yang Dipesan</h3>
            <?php foreach ($items_display as $item): ?>
            <div class="order-item">
                <div class="item-left">
                    <div class="name"><?= htmlspecialchars($item['product_name']) ?></div>
                    <div class="seller">Seller: <?= htmlspecialchars($item['seller_name']) ?></div>
                    <div class="qty-price">
                        <?= $item['qty'] ?> × Rp <?= number_format($item['price'], 0, ',', '.') ?>
                    </div>
                </div>
                <div class="item-right">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="card" style="margin-top: 16px;">
            <h3>📍 Info Pengiriman</h3>
            <div class="info-section">
                <label>Penerima</label>
                <div class="info-box">👤 <?= htmlspecialchars($_SESSION['user_name']) ?></div>
            </div>
            <div class="info-section">
                <label>Metode Pengiriman</label>
                <div class="info-box">🚚 Reguler (2–4 hari kerja) – <span style="color:#27ae60;font-weight:bold;">GRATIS</span></div>
            </div>
            <div class="info-section">
                <label>Metode Pembayaran</label>
                <div class="info-box">💳 Transfer Bank (simulasi)</div>
            </div>
        </div>
    </div>

    <!-- KANAN: Summary & Tombol -->
    <div>
        <div class="card">
            <h3>💰 Ringkasan Pembayaran</h3>
            <div class="summary-row">
                <span>Subtotal Produk</span>
                <span>Rp <?= number_format($total, 0, ',', '.') ?></span>
            </div>
            <div class="summary-row">
                <span>Ongkos Kirim</span>
                <span style="color:#27ae60;">Gratis</span>
            </div>
            <div class="summary-row" style="padding-top: 12px;">
                <span style="font-weight:bold;">Total Pembayaran</span>
                <span class="summary-total">Rp <?= number_format($total, 0, ',', '.') ?></span>
            </div>
            <form method="POST">
                <button type="submit" name="checkout" class="btn-checkout">
                    ✅ Konfirmasi & Buat Pesanan
                </button>
            </form>
        </div>
        <div style="margin-top:12px; font-size:12px; color:#aaa; text-align:center;">
            Dengan menekan tombol di atas, kamu menyetujui syarat dan ketentuan BelanjaMart.
        </div>
    </div>
</div>

</body>
</html>