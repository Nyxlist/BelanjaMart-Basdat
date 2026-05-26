<?php
/**
 * Legacy bridge - kept only so any old code that still calls
 *   include 'config.php';
 *   mysqli_query($conn, ...);
 * keeps working while the codebase is migrated.
 *
 * NEW code should use Database::pdo() and the model classes.
 */
require_once __DIR__ . '/backend/config/bootstrap.php';

if (!isset($conn) || !$conn) {
    $cfg  = config('db');
    $conn = mysqli_connect($cfg['host'], $cfg['username'], $cfg['password'], $cfg['database']);
    if (!$conn) {
        die("Koneksi gagal: " . mysqli_connect_error());
    }
    mysqli_set_charset($conn, $cfg['charset']);
}
