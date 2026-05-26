<?php
/**
 * Tiny global helper functions.  Kept intentionally small.
 *
 * IMPORTANT: only put genuinely cross-cutting helpers here.  Anything
 * with non-trivial logic belongs in a service or model class.
 */

if (!function_exists('config')) {
    /**
     * Read from the global config using "dot.notation".
     * Example: config('db.host')  ->  $GLOBALS['BM_CONFIG']['db']['host']
     */
    function config(string $key, $default = null)
    {
        $segments = explode('.', $key);
        $value    = $GLOBALS['BM_CONFIG'] ?? [];
        foreach ($segments as $seg) {
            if (!is_array($value) || !array_key_exists($seg, $value)) {
                return $default;
            }
            $value = $value[$seg];
        }
        return $value;
    }
}

if (!function_exists('base_url')) {
    /**
     * Prefix a path with the configured base URL.  Always returns a
     * single leading slash and no double slashes.
     */
    function base_url(string $path = ''): string
    {
        $base = rtrim((string) config('app.base_url', ''), '/');
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

if (!function_exists('asset')) {
    /** Build a URL to a frontend asset. */
    function asset(string $path): string
    {
        return base_url('/frontend/assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('e')) {
    /** Short, readable HTML escape. */
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    /** Send a Location header and exit. */
    function redirect(string $path): void
    {
        $url = strpos($path, 'http') === 0 ? $path : base_url($path);
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('flash')) {
    /**
     * Read or write a flash message.
     *   flash('ok', 'Saved!')         - write
     *   flash('ok')                   - read & clear
     */
    function flash(string $key, ?string $value = null)
    {
        if ($value !== null) {
            $_SESSION['__flash'][$key] = $value;
            return null;
        }
        $val = $_SESSION['__flash'][$key] ?? null;
        if ($val !== null) {
            unset($_SESSION['__flash'][$key]);
        }
        return $val;
    }
}

if (!function_exists('csrf_token')) {
    /** Generate (or fetch existing) per-session CSRF token. */
    function csrf_token(): string
    {
        if (empty($_SESSION['__csrf'])) {
            $_SESSION['__csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['__csrf'];
    }
}

if (!function_exists('csrf_field')) {
    /** Render a hidden input containing the CSRF token. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('csrf_check')) {
    /** Validate the CSRF token from POST.  Returns bool. */
    function csrf_check(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return true;
        }
        $sent = $_POST['_csrf'] ?? '';
        return is_string($sent) && hash_equals(csrf_token(), $sent);
    }
}

if (!function_exists('current_user')) {
    /** Return the currently logged-in user array (or null). */
    function current_user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }
}

if (!function_exists('is_logged_in')) {
    function is_logged_in(): bool
    {
        return !empty($_SESSION['user']['user_id']);
    }
}

if (!function_exists('has_role')) {
    function has_role(string $role): bool
    {
        return is_logged_in() && ($_SESSION['user']['role'] ?? '') === $role;
    }
}

if (!function_exists('view')) {
    /**
     * Render a frontend view file with variables injected as locals.
     * View files live under /frontend/pages.
     */
    function view(string $path, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $file = __DIR__ . '/../../frontend/' . ltrim($path, '/');
        if (!is_file($file)) {
            http_response_code(500);
            echo "View not found: " . e($path);
            return;
        }
        include $file;
    }
}

if (!function_exists('component')) {
    /** Include a reusable UI component from /frontend/components. */
    function component(string $name, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $file = __DIR__ . '/../../frontend/components/' . ltrim($name, '/') . '.php';
        if (is_file($file)) {
            include $file;
        }
    }
}

if (!function_exists('layout')) {
    /** Include a layout from /frontend/layouts. */
    function layout(string $name, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $file = __DIR__ . '/../../frontend/layouts/' . ltrim($name, '/') . '.php';
        if (is_file($file)) {
            include $file;
        }
    }
}

if (!function_exists('json_response')) {
    /** Send a JSON response and exit. */
    function json_response($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('input')) {
    /** Read a request input from POST or GET. */
    function input(string $key, $default = null)
    {
        if (array_key_exists($key, $_POST)) return $_POST[$key];
        if (array_key_exists($key, $_GET))  return $_GET[$key];
        return $default;
    }
}

if (!function_exists('logger')) {
    /** Append a one-line message to storage/logs/app.log. */
    function logger(string $msg, string $level = 'info'): void
    {
        $dir  = config('storage.logs');
        if (!$dir) return;
        $line = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), strtoupper($level), $msg);
        @file_put_contents($dir . '/app.log', $line, FILE_APPEND);
    }
}
