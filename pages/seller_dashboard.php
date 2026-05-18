<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . "/../config/config.php";

// 🔒 Harus login & harus seller
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seller') {
    header("Location: ../auth/role.php");
    exit;
}

$seller_id = (int) $_SESSION['user_id'];
$seller_name = htmlspecialchars($_SESSION['user_name']);

// ============================================================
// HANDLE: TAMBAH PRODUK
// ============================================================
if (isset($_POST['action']) && $_POST['action'] === 'tambah') {

    $product_name = trim(mysqli_real_escape_string($conn, $_POST['product_name']));
    $price        = (float) $_POST['price'];
    $stock        = (int)   $_POST['stock'];
    $category_id  = (int)   $_POST['category_id'];

    if (empty($product_name) || $price <= 0 || $stock < 0 || $category_id <= 0) {
        $_SESSION['flash_error'] = "Semua field wajib diisi dengan benar.";
    } else {
        mysqli_query($conn, "
            INSERT INTO products (seller_id, category_id, product_name, price, stock)
            VALUES ($seller_id, $category_id, '$product_name', $price, $stock)
        ");
        $_SESSION['flash_success'] = "Produk berhasil ditambahkan.";
    }
    header("Location: seller_dashboard.php");
    exit;
}

// ============================================================
// HANDLE: EDIT PRODUK
// ============================================================
if (isset($_POST['action']) && $_POST['action'] === 'edit') {

    $product_id   = (int)   $_POST['product_id'];
    $product_name = trim(mysqli_real_escape_string($conn, $_POST['product_name']));
    $price        = (float) $_POST['price'];
    $stock        = (int)   $_POST['stock'];
    $category_id  = (int)   $_POST['category_id'];

    // Pastikan produk ini milik seller yang login
    $own = mysqli_query($conn, "
        SELECT product_id FROM products
        WHERE product_id = $product_id AND seller_id = $seller_id
    ");

    if (mysqli_num_rows($own) === 0) {
        $_SESSION['flash_error'] = "Produk tidak ditemukan atau bukan milik Anda.";
    } elseif (empty($product_name) || $price <= 0 || $stock < 0 || $category_id <= 0) {
        $_SESSION['flash_error'] = "Semua field wajib diisi dengan benar.";
    } else {
        mysqli_query($conn, "
            UPDATE products
            SET product_name = '$product_name',
                price        = $price,
                stock        = $stock,
                category_id  = $category_id
            WHERE product_id = $product_id AND seller_id = $seller_id
        ");
        $_SESSION['flash_success'] = "Produk berhasil diupdate.";
    }
    header("Location: seller_dashboard.php");
    exit;
}

// ============================================================
// HANDLE: HAPUS PRODUK
// ============================================================
if (isset($_GET['hapus'])) {
    $product_id = (int) $_GET['hapus'];

    // Pastikan produk milik seller yang login
    $own = mysqli_query($conn, "
        SELECT product_id FROM products
        WHERE product_id = $product_id AND seller_id = $seller_id
    ");

    if (mysqli_num_rows($own) === 0) {
        $_SESSION['flash_error'] = "Produk tidak ditemukan atau bukan milik Anda.";
    } else {
        mysqli_query($conn, "
            DELETE FROM products WHERE product_id = $product_id AND seller_id = $seller_id
        ");
        $_SESSION['flash_success'] = "Produk berhasil dihapus.";
    }
    header("Location: seller_dashboard.php");
    exit;
}

// ============================================================
// AMBIL DATA
// ============================================================

// Daftar kategori
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");

// Daftar produk milik seller ini
$products = mysqli_query($conn, "
    SELECT p.*, c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.seller_id = $seller_id
    ORDER BY p.created_at DESC
");

// Pesanan masuk: order items yang produknya milik seller ini
$orders = mysqli_query($conn, "
    SELECT
        o.order_id,
        o.order_date,
        o.status,
        o.tracking_number,
        u.name   AS buyer_name,
        p.product_name,
        oi.quantity,
        oi.price AS item_price,
        (oi.quantity * oi.price) AS subtotal
    FROM orders o
    JOIN order_items oi ON o.order_id   = oi.order_id
    JOIN products p     ON oi.product_id = p.product_id
    JOIN users u        ON o.user_id     = u.user_id
    WHERE p.seller_id = $seller_id
    ORDER BY o.order_date DESC
");

// Flash messages
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error']   ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Data produk untuk mode edit (dari URL ?edit=ID)
$edit_product = null;
if (isset($_GET['edit'])) {
    $edit_id = (int) $_GET['edit'];
    $eq = mysqli_query($conn, "
        SELECT * FROM products WHERE product_id = $edit_id AND seller_id = $seller_id
    ");
    $edit_product = mysqli_fetch_assoc($eq);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Seller Dashboard – BelanjaMart</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; }

        /* ── NAV ── */
        .topbar {
            background: #2c3e50; color: white;
            padding: 12px 20px; display: flex;
            justify-content: space-between; align-items: center;
        }
        .topbar a { color: #ecf0f1; text-decoration: none; font-size: 14px; }
        .topbar a:hover { text-decoration: underline; }

        /* ── LAYOUT ── */
        .wrapper { max-width: 960px; margin: 24px auto; padding: 0 16px; }

        /* ── FLASH ── */
        .flash-ok  { background: #d4edda; color: #155724; border: 1px solid #c3e6cb;
                     padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }
        .flash-err { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;
                     padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; }

        /* ── CARD ── */
        .card {
            background: white; border-radius: 10px;
            padding: 20px; margin-bottom: 24px;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
        }
        .card h2 { margin-top: 0; font-size: 18px; border-bottom: 2px solid #eee; padding-bottom: 8px; }

        /* ── FORM ── */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-grid .full { grid-column: 1 / -1; }
        label { display: block; font-size: 13px; color: #555; margin-bottom: 4px; }
        input[type=text], input[type=number], select, textarea {
            width: 100%; padding: 8px 10px; border: 1px solid #ccc;
            border-radius: 6px; font-size: 14px;
        }
        .btn { padding: 9px 18px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .btn-green  { background: #27ae60; color: white; }
        .btn-green:hover { background: #219a52; }
        .btn-blue   { background: #2980b9; color: white; }
        .btn-blue:hover  { background: #2471a3; }
        .btn-red    { background: #e74c3c; color: white; }
        .btn-red:hover   { background: #c0392b; }
        .btn-gray   { background: #95a5a6; color: white; }
        .btn-gray:hover  { background: #7f8c8d; }

        /* ── TABLE ── */
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { background: #ecf0f1; text-align: left; padding: 9px 10px; }
        td { padding: 8px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        tr:hover td { background: #fafafa; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 20px;
            font-size: 12px; font-weight: bold;
        }
        .badge-pending   { background: #ffeeba; color: #856404; }
        .badge-shipped   { background: #bee5eb; color: #0c5460; }
        .badge-delivered { background: #c3e6cb; color: #155724; }
        .badge-cancelled { background: #f5c6cb; color: #721c24; }

        .empty-note { color: #aaa; font-style: italic; padding: 12px 0; }
    </style>
</head>
<body>

<!-- ── TOP NAV ── -->
<div class="topbar">
    <span>🛒 <strong>BelanjaMart</strong> — Seller Dashboard</span>
    <span>
        👤 <?= $seller_name ?> &nbsp;|&nbsp;
        <a href="../auth/logout.php">Logout</a>
    </span>
</div>

<div class="wrapper">

    <!-- FLASH MESSAGES -->
    <?php if ($flash_success): ?>
        <div class="flash-ok">✅ <?= htmlspecialchars($flash_success) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="flash-err">❌ <?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>

    <!-- ════════════════════════════════════════════
         FORM: TAMBAH / EDIT PRODUK
    ═════════════════════════════════════════════ -->
    <div class="card">
        <?php if ($edit_product): ?>
            <h2>✏️ Edit Produk</h2>
            <form method="POST">
                <input type="hidden" name="action"     value="edit">
                <input type="hidden" name="product_id" value="<?= $edit_product['product_id'] ?>">
                <div class="form-grid">
                    <div class="full">
                        <label>Nama Produk</label>
                        <input type="text" name="product_name"
                               value="<?= htmlspecialchars($edit_product['product_name']) ?>" required>
                    </div>
                    <div>
                        <label>Harga (Rp)</label>
                        <input type="number" name="price" min="0" step="100"
                               value="<?= $edit_product['price'] ?>" required>
                    </div>
                    <div>
                        <label>Stok</label>
                        <input type="number" name="stock" min="0"
                               value="<?= $edit_product['stock'] ?>" required>
                    </div>
                    <div class="full">
                        <label>Kategori</label>
                        <select name="category_id" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php
                            mysqli_data_seek($categories, 0);
                            while ($cat = mysqli_fetch_assoc($categories)):
                                $sel = ($cat['category_id'] == $edit_product['category_id']) ? 'selected' : '';
                            ?>
                                <option value="<?= $cat['category_id'] ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-blue">💾 Simpan Perubahan</button>
                        &nbsp;
                        <a href="seller_dashboard.php" class="btn btn-gray" style="text-decoration:none;">Batal</a>
                    </div>
                </div>
            </form>

        <?php else: ?>
            <h2>➕ Tambah Produk Baru</h2>
            <form method="POST">
                <input type="hidden" name="action" value="tambah">
                <div class="form-grid">
                    <div class="full">
                        <label>Nama Produk</label>
                        <input type="text" name="product_name" placeholder="Contoh: Sepatu Nike Air Max" required>
                    </div>
                    <div>
                        <label>Harga (Rp)</label>
                        <input type="number" name="price" min="0" step="100" placeholder="150000" required>
                    </div>
                    <div>
                        <label>Stok</label>
                        <input type="number" name="stock" min="0" placeholder="10" required>
                    </div>
                    <div class="full">
                        <label>Kategori</label>
                        <select name="category_id" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php
                            mysqli_data_seek($categories, 0);
                            while ($cat = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?= $cat['category_id'] ?>">
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-green">➕ Tambah Produk</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════════════
         TABEL: DAFTAR PRODUK SAYA
    ═════════════════════════════════════════════ -->
    <div class="card">
        <h2>📦 Produk Saya</h2>
        <?php if (mysqli_num_rows($products) === 0): ?>
            <p class="empty-note">Belum ada produk. Tambahkan produk pertama Anda di atas!</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Produk</th>
                    <th>Kategori</th>
                    <th>Harga</th>
                    <th>Stok</th>
                    <th>Rating</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php $no = 1; while ($p = mysqli_fetch_assoc($products)): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($p['product_name']) ?></td>
                    <td><?= htmlspecialchars($p['category_name']) ?></td>
                    <td>Rp <?= number_format($p['price'], 0, ',', '.') ?></td>
                    <td><?= $p['stock'] ?></td>
                    <td>
                        ⭐ <?= number_format($p['average_rating'], 1) ?>/5
                        <small>(<?= $p['total_reviews'] ?>)</small>
                    </td>
                    <td>
                        <a href="seller_dashboard.php?edit=<?= $p['product_id'] ?>"
                           class="btn btn-blue" style="text-decoration:none; font-size:12px;">
                           ✏️ Edit
                        </a>
                        &nbsp;
                        <a href="seller_dashboard.php?hapus=<?= $p['product_id'] ?>"
                           class="btn btn-red" style="text-decoration:none; font-size:12px;"
                           onclick="return confirm('Yakin ingin menghapus produk ini?')">
                           🗑️ Hapus
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ════════════════════════════════════════════
         TABEL: PESANAN MASUK
    ═════════════════════════════════════════════ -->
    <div class="card">
        <h2>📋 Pesanan Masuk</h2>
        <?php if (mysqli_num_rows($orders) === 0): ?>
            <p class="empty-note">Belum ada pesanan masuk.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Tanggal</th>
                    <th>Pembeli</th>
                    <th>Produk</th>
                    <th>Qty</th>
                    <th>Subtotal</th>
                    <th>Status</th>
                    <th>No. Resi</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($ord = mysqli_fetch_assoc($orders)): ?>
                <tr>
                    <td>#<?= $ord['order_id'] ?></td>
                    <td><?= date('d M Y', strtotime($ord['order_date'])) ?></td>
                    <td><?= htmlspecialchars($ord['buyer_name']) ?></td>
                    <td><?= htmlspecialchars($ord['product_name']) ?></td>
                    <td><?= $ord['quantity'] ?></td>
                    <td>Rp <?= number_format($ord['subtotal'], 0, ',', '.') ?></td>
                    <td>
                        <span class="badge badge-<?= $ord['status'] ?>">
                            <?= ucfirst($ord['status']) ?>
                        </span>
                    </td>
                    <td><?= $ord['tracking_number'] ? htmlspecialchars($ord['tracking_number']) : '<span style="color:#aaa">-</span>' ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div><!-- /wrapper -->
</body>
</html>