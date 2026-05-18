<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . "/../config/config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/role.php");
    exit;
}

$role = $_SESSION['role'];
if ($role == 'seller') {
    header("Location: seller_dashboard.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Filter kategori
$filter_cat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$filter_search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Ambil semua kategori
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");

// Ambil produk (dengan filter)
$where = "WHERE p.stock > 0";
if ($filter_cat > 0) $where .= " AND p.category_id = $filter_cat";
if ($filter_search !== '') {
    $qs = mysqli_real_escape_string($conn, $filter_search);
    $where .= " AND p.product_name LIKE '%$qs%'";
}

$products = mysqli_query($conn, "
    SELECT p.*, c.category_name, s.name AS seller_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN sellers s ON p.seller_id = s.seller_id
    $where
    ORDER BY p.created_at DESC
");

// Hitung jumlah item di keranjang
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) $cart_count += $item['qty'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BelanjaMart – Belanja Mudah & Murah</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; color: #333; }

        /* ── TOPBAR ── */
        .topbar {
            background: #c0392b;
            color: white;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 56px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,.2);
        }
        .topbar .logo { font-size: 22px; font-weight: bold; letter-spacing: 1px; }
        .topbar .logo span { color: #f9ca24; }
        .search-bar {
            flex: 1;
            max-width: 480px;
            margin: 0 20px;
            display: flex;
        }
        .search-bar input {
            flex: 1;
            padding: 8px 14px;
            border: none;
            border-radius: 4px 0 0 4px;
            font-size: 14px;
        }
        .search-bar button {
            padding: 8px 16px;
            background: #f9ca24;
            color: #333;
            border: none;
            border-radius: 0 4px 4px 0;
            font-weight: bold;
            cursor: pointer;
        }
        .topbar-right { display: flex; align-items: center; gap: 16px; }
        .topbar-right a { color: white; text-decoration: none; font-size: 14px; }
        .cart-btn {
            background: #f9ca24;
            color: #333 !important;
            padding: 7px 14px;
            border-radius: 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .cart-badge {
            background: #c0392b;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .user-info { font-size: 13px; opacity: .85; }

        /* ── LAYOUT ── */
        .main-wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; display: flex; gap: 20px; }
        
        /* ── SIDEBAR ── */
        .sidebar { width: 200px; flex-shrink: 0; }
        .sidebar .card { background: white; border-radius: 10px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .sidebar h3 { font-size: 14px; color: #888; text-transform: uppercase; margin-bottom: 12px; }
        .cat-link {
            display: block;
            padding: 8px 10px;
            border-radius: 6px;
            text-decoration: none;
            color: #555;
            font-size: 14px;
            margin-bottom: 2px;
            transition: background .15s;
        }
        .cat-link:hover, .cat-link.active { background: #fdecea; color: #c0392b; font-weight: bold; }

        /* ── PRODUK GRID ── */
        .content { flex: 1; min-width: 0; }
        .section-title { font-size: 18px; font-weight: bold; margin-bottom: 16px; color: #444; }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }
        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            transition: transform .2s, box-shadow .2s;
        }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 4px 16px rgba(0,0,0,.12); }
        .product-img {
            background: #f0f2f5;
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #ccc;
        }
        .product-body { padding: 12px; }
        .product-name { font-size: 14px; font-weight: 600; margin-bottom: 4px; line-height: 1.3; height: 36px; overflow: hidden; }
        .product-seller { font-size: 12px; color: #888; margin-bottom: 6px; }
        .product-seller span { color: #c0392b; font-weight: 500; }
        .product-category { font-size: 11px; background: #fdecea; color: #c0392b; padding: 2px 8px; border-radius: 10px; display: inline-block; margin-bottom: 8px; }
        .product-price { font-size: 16px; font-weight: bold; color: #c0392b; margin-bottom: 4px; }
        .product-rating { font-size: 12px; color: #888; margin-bottom: 10px; }
        .product-stock { font-size: 12px; color: #27ae60; margin-bottom: 10px; }
        .btn-cart {
            display: block;
            width: 100%;
            background: #c0392b;
            color: white;
            border: none;
            padding: 9px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
            transition: background .15s;
        }
        .btn-cart:hover { background: #a93226; }
        .empty-state { text-align: center; padding: 60px 20px; color: #aaa; }
        .empty-state .icon { font-size: 64px; margin-bottom: 12px; }

        /* ── FLASH ── */
        .flash { padding: 10px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .flash-ok  { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .flash-err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="logo">Belanja<span>Mart</span></div>
    <form class="search-bar" method="GET">
        <?php if ($filter_cat > 0): ?>
            <input type="hidden" name="cat" value="<?= $filter_cat ?>">
        <?php endif; ?>
        <input type="text" name="q" placeholder="Cari produk..." value="<?= htmlspecialchars($filter_search) ?>">
        <button type="submit">🔍</button>
    </form>
    <div class="topbar-right">
        <span class="user-info">👋 <?= htmlspecialchars($_SESSION['user_name']) ?></span>
        <a href="orders.php">📦 Pesanan</a>
        <a href="cart.php" class="cart-btn">
            🛒 Keranjang
            <?php if ($cart_count > 0): ?>
                <span class="cart-badge"><?= $cart_count ?></span>
            <?php endif; ?>
        </a>
        <a href="../auth/logout.php">Keluar</a>
    </div>
</div>

<!-- MAIN -->
<div class="main-wrap">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="card">
            <h3>Kategori</h3>
            <a href="index.php" class="cat-link <?= $filter_cat == 0 ? 'active' : '' ?>">🏠 Semua</a>
            <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                <a href="index.php?cat=<?= $cat['category_id'] ?>" 
                   class="cat-link <?= $filter_cat == $cat['category_id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['category_name']) ?>
                </a>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- PRODUK -->
    <div class="content">

        <?php if (isset($_SESSION['flash_ok'])): ?>
            <div class="flash flash-ok">✅ <?= $_SESSION['flash_ok'] ?></div>
            <?php unset($_SESSION['flash_ok']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['flash_err'])): ?>
            <div class="flash flash-err">❌ <?= $_SESSION['flash_err'] ?></div>
            <?php unset($_SESSION['flash_err']); ?>
        <?php endif; ?>

        <div class="section-title">
            🛍️ Produk Tersedia
            <?php if ($filter_search): ?>
                – Hasil pencarian "<b><?= htmlspecialchars($filter_search) ?></b>"
            <?php endif; ?>
        </div>

        <?php if (!$products || mysqli_num_rows($products) == 0): ?>
            <div class="empty-state">
                <div class="icon">🔍</div>
                <p>Produk tidak ditemukan.</p>
            </div>
        <?php else: ?>
        <div class="product-grid">
            <?php while ($p = mysqli_fetch_assoc($products)): ?>
            <div class="product-card">
                <div class="product-img">📦</div>
                <div class="product-body">
                    <div class="product-name"><?= htmlspecialchars($p['product_name']) ?></div>
                    <div class="product-seller">Dijual oleh: <span><?= htmlspecialchars($p['seller_name']) ?></span></div>
                    <span class="product-category"><?= htmlspecialchars($p['category_name']) ?></span>
                    <div class="product-price">Rp <?= number_format($p['price'], 0, ',', '.') ?></div>
                    <div class="product-rating">⭐ <?= number_format($p['average_rating'], 1) ?>/5 (<?= $p['total_reviews'] ?> ulasan)</div>
                    <div class="product-stock">Stok: <?= $p['stock'] ?></div>
                    <a href="cart.php?add=<?= $p['product_id'] ?>" class="btn-cart">🛒 Tambah ke Keranjang</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>