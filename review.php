<?php
include 'config.php';

if (isset($_POST['submit'])) {
    $order_item_id = $_POST['order_item_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];

    $query = "INSERT INTO reviews (order_item_id, rating, comment)
              VALUES ('$order_item_id', '$rating', '$comment')";

    if (mysqli_query($conn, $query)) {
        echo "Review berhasil!";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<form method="POST">
    Order Item ID: <input type="text" name="order_item_id"><br>
    Rating: <input type="number" name="rating"><br>
    Comment: <textarea name="comment"></textarea><br>
    <button name="submit">Submit</button>
</form>