<?php
/** Legacy bridge for old `pages/*.php` files. */
require_once __DIR__ . '/../backend/config/bootstrap.php';

if (!isset($conn) || !$conn) {
    $cfg  = config('db');
    $conn = mysqli_connect($cfg['host'], $cfg['username'], $cfg['password'], $cfg['database']);
    if (!$conn) {
        die("Koneksi gagal: " . mysqli_connect_error());
    }
    mysqli_set_charset($conn, $cfg['charset']);
}
