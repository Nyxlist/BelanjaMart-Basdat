<?php
include 'config.php';

$products = mysqli_query($conn, "SELECT * FROM products");
?>

<!DOCTYPE html>
<html>
<head>
    <title>BelanjaMart</title>
</head>
<body>

<h1>Daftar Produk</h1>

<?php while ($p = mysqli_fetch_assoc($products)) { ?>
    <div style="border:1px solid #000; margin:10px; padding:10px;">
        
        <h3><?php echo $p['product_name']; ?></h3>
        <p>Harga: Rp <?php echo $p['price']; ?></p>
        <p>Rating Produk: <?php echo $p['average_rating']; ?></p>

        <h4>Review:</h4>

        <?php
        $reviews = mysqli_query($conn, "
            SELECT r.rating, r.comment 
            FROM reviews r
            JOIN order_items oi ON r.order_item_id = oi.order_item_id
            WHERE oi.product_id = " . $p['product_id']
        );

        if (mysqli_num_rows($reviews) == 0) {
            echo "<i>Belum ada review</i>";
        } else {
            while ($r = mysqli_fetch_assoc($reviews)) {
                echo "<p>Rating: " . $r['rating'] . "/5</p>";
                echo "<p>Komentar: " . $r['comment'] . "</p>";
                echo "<hr>";
            }
        }
        ?>

    </div>
<?php } ?>

</body>
</html>
