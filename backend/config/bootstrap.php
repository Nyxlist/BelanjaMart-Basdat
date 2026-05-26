<?php
/**
 * Bootstrap - the single file every entry point should `require_once`.
 *
 * Responsibilities:
 *   - Load environment + configuration
 *   - Configure error reporting and timezone
 *   - Start a session (only when running inside a web request)
 *   - Register a tiny PSR-style autoloader for backend classes
 *   - Expose helper functions used across the codebase
 */

if (!defined('BM_BOOTSTRAPPED')) {
    define('BM_BOOTSTRAPPED', true);

    // ---------- Load env ----------
    $GLOBALS['BM_CONFIG'] = require __DIR__ . '/env.php';
    $cfg = $GLOBALS['BM_CONFIG'];

    // ---------- Error reporting ----------
    if (!empty($cfg['app']['debug'])) {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    } else {
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
        ini_set('display_errors', '0');
    }

    date_default_timezone_set($cfg['app']['timezone'] ?? 'UTC');

    // ---------- Session ----------
    if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
        // SameSite=Lax is a sensible default for a typical web app
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    // ---------- Autoloader ----------
    spl_autoload_register(function ($class) {
        // Look under backend/{core,models,services,controllers,middleware}
        $roots = [
            __DIR__ . '/../core/',
            __DIR__ . '/../models/',
            __DIR__ . '/../services/',
            __DIR__ . '/../controllers/',
            __DIR__ . '/../middleware/',
        ];
        foreach ($roots as $root) {
            $file = $root . $class . '.php';
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    });

    // ---------- Storage dirs ----------
    foreach (['uploads', 'chat', 'logs'] as $key) {
        $path = $cfg['storage'][$key] ?? null;
        if ($path && !is_dir($path)) {
            @mkdir($path, 0777, true);
        }
    }

    // ---------- Global helpers ----------
    require_once __DIR__ . '/../core/helpers.php';
}
