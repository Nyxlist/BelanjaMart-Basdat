<?php
/**
 * Language switcher endpoint.
 * GET ?lang=en|id|ja|zh  -> sets locale in session and redirects back.
 */
require_once __DIR__ . '/../../../backend/config/bootstrap.php';

$locale = $_GET['lang'] ?? 'en';
Lang::setLocale($locale);

// Redirect back to where the user came from
$ref = $_SERVER['HTTP_REFERER'] ?? base_url('/');
header('Location: ' . $ref);
exit;
