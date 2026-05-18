<?php
include 'config/config.php';

$user_id = 1; // sementara (belum login)

// ambil order_item_id dari URL, bukan dari input user
$order_item_id = $_GET['order_item_id'] ?? 0;

// 🔒 cek validasi (harus delivered & milik user)
$cek = mysqli_query($conn, "
SELECT oi.order_item_id, oi.product_id, o.status
FROM order_items oi
JOIN orders o ON oi.order_id = o.order_id
WHERE oi.order_item_id = $order_item_id
AND o.user_id = $user_id
");

$data = mysqli_fetch_assoc($cek);

// kalau tidak valid
if (!$data) {
    die("❌ Data tidak valid");
}

// kalau belum delivered
if ($data['status'] != 'delivered') {
    die("❌ Belum bisa review (pesanan belum diterima)");
}

// 🔒 cek sudah pernah review atau belum
$cek2 = mysqli_query($conn, "
SELECT * FROM reviews WHERE order_item_id = $order_item_id
");

if (mysqli_num_rows($cek2) > 0) {
    die("❌ Kamu sudah review produk ini");
}

// ambil product_id
$product_id = $data['product_id'];
?>

<h2>Tulis Review</h2>

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

    <button name="submit">Kirim Review</button>
</form>

<?php
if (isset($_POST['submit'])) {

    $rating = (int) $_POST['rating'];
    $comment = mysqli_real_escape_string($conn, $_POST['comment']);

    $query = "INSERT INTO reviews (order_item_id, rating, comment)
              VALUES ($order_item_id, $rating, '$comment')";

    if (mysqli_query($conn, $query)) {
        echo "✅ Review berhasil!<br>";
        echo "<a href='index.php'>Kembali</a>";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>