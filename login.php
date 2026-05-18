<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
header("Location: auth/login.php" . ($query ? "?$query" : ""));
exit;