<?php
/**
 * Environment configuration.
 *
 * Kept as a plain PHP array for simplicity. In a larger deployment you
 * would load these from a `.env` file using something like
 * vlucas/phpdotenv. The structure here mirrors that.
 */

return [
    // ---------- Application ----------
    'app' => [
        'name'      => 'BelanjaMart',
        'env'       => 'development',          // development | production
        'debug'     => true,
        'base_url'  => '/belanjamart',         // URL prefix when served from htdocs
        'timezone'  => 'Asia/Jakarta',
    ],

    // ---------- Database ----------
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'belanjamart',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    // ---------- JWT ----------
    'jwt' => [
        // Change this for any deployment. The default is fine for local dev.
        'secret'      => 'belanjamart-local-secret-change-me',
        'algo'        => 'HS256',
        'ttl_seconds' => 60 * 60 * 24 * 7,     // 7 days
        'issuer'      => 'belanjamart',
    ],

    // ---------- Storage paths ----------
    'storage' => [
        'root'    => __DIR__ . '/../../storage',
        'uploads' => __DIR__ . '/../../storage/uploads',
        'chat'    => __DIR__ . '/../../storage/chat',
        'logs'    => __DIR__ . '/../../storage/logs',
    ],

    // ---------- Rate limiting ----------
    'rate_limit' => [
        'auth_per_minute'  => 8,
        'api_per_minute'   => 120,
    ],

    // ---------- Defaults ----------
    'defaults' => [
        'currency' => 'IDR',
        'country'  => 'ID',
        'locale'   => 'id-ID',
    ],
];
