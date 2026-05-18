<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . "/../config/config.php";

// 🔒 harus login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/role.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$order_item_id = (int) ($_GET['order_item_id'] ?? 0);

// 🔒 ambil data order + produk
$cek = mysqli_query($conn, "
SELECT oi.order_item_id, oi.product_id, p.product_name, p.price, o.status
FROM order_items oi
JOIN orders o ON oi.order_id = o.order_id
JOIN products p ON oi.product_id = p.product_id
WHERE oi.order_item_id = $order_item_id
AND o.user_id = $user_id
");

$data = mysqli_fetch_assoc($cek);

if (!$data) {
    die("❌ Data tidak valid");
}

if ($data['status'] != 'delivered') {
    die("❌ Belum bisa review");
}

// 🔒 cek sudah review atau belum
$cek2 = mysqli_query($conn, "
SELECT * FROM reviews WHERE order_item_id = $order_item_id
");

$alreadyReviewed = mysqli_num_rows($cek2) > 0;

// ambil semua review
$reviews = mysqli_query($conn, "
SELECT r.rating, r.comment, r.created_at, u.name
FROM reviews r
JOIN order_items oi ON r.order_item_id = oi.order_item_id
JOIN orders o ON oi.order_id = o.order_id
JOIN users u ON o.user_id = u.user_id
WHERE oi.product_id = {$data['product_id']}
ORDER BY r.created_at DESC
");

// rating
$rating = mysqli_query($conn, "
SELECT ROUND(AVG(r.rating),1) as avg_rating, COUNT(*) as total
FROM reviews r
JOIN order_items oi ON r.order_item_id = oi.order_item_id
WHERE oi.product_id = {$data['product_id']}
");

$r = mysqli_fetch_assoc($rating);

// 🔥 HANDLE SUBMIT
if (isset($_POST['submit']) && !$alreadyReviewed) {

    $rating_val = (int) $_POST['rating'];
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    mysqli_query($conn, "
    INSERT INTO reviews (order_item_id, rating, comment)
    VALUES ($order_item_id, $rating_val, '$comment')
    ");

    header("Location: product.php?order_item_id=$order_item_id");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Detail Produk</title>
</head>
<body>

<h1><?= htmlspecialchars($data['product_name']) ?></h1>
<p>Rp <?= number_format($data['price']) ?></p>

<p>
⭐ <?= $r['avg_rating'] ?? 0 ?> / 5
(<?= $r['total'] ?? 0 ?> review)
</p>

<hr>

<h2>Review</h2>

<?php if (mysqli_num_rows($reviews) == 0) { ?>
    <i>Belum ada review</i>
<?php } else {
    while ($rev = mysqli_fetch_assoc($reviews)) { ?>
        <div>
            <b><?= htmlspecialchars($rev['name']) ?></b><br>
            ⭐ <?= $rev['rating'] ?>/5<br>
            <?= htmlspecialchars($rev['comment']) ?><br>
            <small><?= $rev['created_at'] ?></small>
        </div>
<?php } } ?>

<hr>

<h2>Tulis Review</h2>

<?php if ($alreadyReviewed) { ?>
    <p>❌ Kamu sudah review</p>
<?php } else { ?>

<form method="POST">
    Rating:
    <select name="rating">
        <option value="5">⭐⭐⭐⭐⭐</option>
        <option value="4">⭐⭐⭐⭐</option>
        <option value="3">⭐⭐⭐</option>
        <option value="2">⭐⭐</option>
        <option value="1">⭐</option>
    </select><br><br>

    Komentar:<br>
    <textarea name="comment" required></textarea><br><br>

    <button name="submit">Kirim</button>
</form>

<?php } ?>

<br>
<a href="index.php">⬅ Kembali</a>

</body>
</html>