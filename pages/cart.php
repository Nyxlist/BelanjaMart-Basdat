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

// ── TAMBAH KE KERANJANG ──
if (isset($_GET['add'])) {
    $pid = (int) $_GET['add'];
    $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM products WHERE product_id = $pid AND stock > 0"));
    if ($p) {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        if (isset($_SESSION['cart'][$pid])) {
            // jangan melebihi stok
            if ($_SESSION['cart'][$pid]['qty'] < $p['stock']) {
                $_SESSION['cart'][$pid]['qty']++;
            } else {
                $_SESSION['flash_err'] = "Stok tidak mencukupi!";
            }
        } else {
            $_SESSION['cart'][$pid] = [
                'product_id'   => $pid,
                'product_name' => $p['product_name'],
                'price'        => $p['price'],
                'seller_name'  => '', // akan diisi nanti
                'qty'          => 1
            ];
        }
        $_SESSION['flash_ok'] = "Produk ditambahkan ke keranjang!";
    }
    header("Location: index.php");
    exit;
}

// ── UPDATE QTY ──
if (isset($_POST['update'])) {
    foreach ($_POST['qty'] as $pid => $qty) {
        $pid = (int) $pid;
        $qty = (int) $qty;
        $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT stock FROM products WHERE product_id = $pid"));
        if ($p) {
            if ($qty <= 0) {
                unset($_SESSION['cart'][$pid]);
            } else {
                $qty = min($qty, $p['stock']);
                $_SESSION['cart'][$pid]['qty'] = $qty;
            }
        }
    }
    header("Location: cart.php");
    exit;
}

// ── HAPUS ITEM ──
if (isset($_GET['remove'])) {
    $pid = (int) $_GET['remove'];
    unset($_SESSION['cart'][$pid]);
    header("Location: cart.php");
    exit;
}

// ── HITUNG TOTAL ──
$cart = $_SESSION['cart'] ?? [];
$total = 0;

// Enrich seller name
foreach ($cart as $pid => &$item) {
    $sp = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT s.name AS seller_name, p.stock FROM products p
        JOIN sellers s ON p.seller_id = s.seller_id
        WHERE p.product_id = $pid
    "));
    if ($sp) {
        $item['seller_name'] = $sp['seller_name'];
        $item['stock']       = $sp['stock'];
    }
    $total += $item['price'] * $item['qty'];
}
unset($item);

$cart_count = 0;
foreach ($cart as $c) $cart_count += $c['qty'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang – BelanjaMart</title>
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

        .card { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); overflow: hidden; margin-bottom: 20px; }

        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8f9fa; }
        th { padding: 12px 16px; text-align: left; font-size: 13px; color: #888; font-weight: 600; }
        td { padding: 14px 16px; border-top: 1px solid #f0f0f0; vertical-align: middle; }

        .item-name { font-weight: 600; font-size: 15px; }
        .item-seller { font-size: 12px; color: #888; margin-top: 2px; }
        .item-price { color: #c0392b; font-weight: bold; font-size: 15px; }

        .qty-input {
            width: 60px; text-align: center;
            padding: 6px; border: 1px solid #ddd;
            border-radius: 6px; font-size: 14px;
        }
        .btn-remove {
            color: #e74c3c; border: none; background: none;
            cursor: pointer; font-size: 18px; padding: 4px;
        }
        .btn-remove:hover { color: #c0392b; }
        .btn-update {
            background: #3498db; color: white; border: none;
            padding: 9px 18px; border-radius: 6px; cursor: pointer;
            font-size: 14px; font-weight: 500;
        }
        .btn-update:hover { background: #2980b9; }

        .summary { background: white; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,.08); padding: 20px; }
        .summary-row { display: flex; justify-content: space-between; padding: 8px 0; font-size: 15px; border-bottom: 1px solid #f0f0f0; }
        .summary-row:last-child { border-bottom: none; }
        .summary-total { font-size: 20px; font-weight: bold; color: #c0392b; }
        .btn-checkout {
            display: block; width: 100%; background: #c0392b; color: white;
            border: none; padding: 14px; border-radius: 8px; cursor: pointer;
            font-size: 16px; font-weight: bold; text-align: center; margin-top: 16px;
            text-decoration: none; transition: background .15s;
        }
        .btn-checkout:hover { background: #a93226; }
        .btn-back { color: #c0392b; text-decoration: none; font-size: 14px; display: inline-block; margin-bottom: 12px; }
        .btn-back:hover { text-decoration: underline; }

        .empty-cart { text-align: center; padding: 60px; color: #aaa; }
        .empty-cart .icon { font-size: 64px; margin-bottom: 16px; }
        .empty-cart p { font-size: 16px; margin-bottom: 20px; }
        .btn-shop { display: inline-block; background: #c0392b; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; }

        .flash { padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .flash-ok  { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .flash-err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<div class="topbar">
    <div class="logo">Belanja<span>Mart</span></div>
    <div class="topbar-right">
        <a href="index.php">🏠 Belanja</a>
        <a href="orders.php">📦 Pesanan Saya</a>
        <a href="../auth/logout.php">Keluar</a>
    </div>
</div>

<div class="wrap">
    <a href="index.php" class="btn-back">← Lanjut Belanja</a>
    <div class="page-title">🛒 Keranjang Belanja</div>

    <?php if (isset($_SESSION['flash_ok'])): ?>
        <div class="flash flash-ok">✅ <?= $_SESSION['flash_ok'] ?></div>
        <?php unset($_SESSION['flash_ok']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_err'])): ?>
        <div class="flash flash-err">❌ <?= $_SESSION['flash_err'] ?></div>
        <?php unset($_SESSION['flash_err']); ?>
    <?php endif; ?>

    <?php if (empty($cart)): ?>
        <div class="card">
            <div class="empty-cart">
                <div class="icon">🛒</div>
                <p>Keranjang kamu masih kosong.</p>
                <a href="index.php" class="btn-shop">Mulai Belanja</a>
            </div>
        </div>
    <?php else: ?>

    <form method="POST">
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Harga Satuan</th>
                        <th>Jumlah</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $pid => $item): ?>
                    <tr>
                        <td>
                            <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                            <div class="item-seller">Seller: <?= htmlspecialchars($item['seller_name']) ?></div>
                        </td>
                        <td class="item-price">Rp <?= number_format($item['price'], 0, ',', '.') ?></td>
                        <td>
                            <input type="number" name="qty[<?= $pid ?>]"
                                   value="<?= $item['qty'] ?>"
                                   min="0" max="<?= $item['stock'] ?? 99 ?>"
                                   class="qty-input">
                        </td>
                        <td class="item-price">Rp <?= number_format($item['price'] * $item['qty'], 0, ',', '.') ?></td>
                        <td>
                            <a href="cart.php?remove=<?= $pid ?>" class="btn-remove" title="Hapus">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" name="update" class="btn-update">🔄 Update Keranjang</button>
    </form>

    <div class="summary" style="margin-top: 20px;">
        <div class="summary-row">
            <span>Total Item</span>
            <span><?= $cart_count ?> item</span>
        </div>
        <div class="summary-row">
            <span>Ongkir</span>
            <span style="color:#27ae60;">Gratis</span>
        </div>
        <div class="summary-row">
            <span>Total Bayar</span>
            <span class="summary-total">Rp <?= number_format($total, 0, ',', '.') ?></span>
        </div>
        <a href="checkout.php" class="btn-checkout">✅ Lanjut ke Checkout</a>
    </div>

    <?php endif; ?>
</div>

</body>
</html>