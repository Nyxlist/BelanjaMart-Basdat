<?php
$conn = mysqli_connect("localhost", "root", "", "belanjamart");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>