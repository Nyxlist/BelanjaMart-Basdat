<?php
/**
 * Middleware that guards web pages and API endpoints.
 *
 * Web pages   -> use AuthMiddleware::requireWeb()  (session-based)
 * API methods -> use AuthMiddleware::requireApi()  (JWT-based, returns user)
 */

class AuthMiddleware
{
    /* ---------------- WEB (session) ---------------- */

    public static function requireWeb(?string $role = null): void
    {
        if (!is_logged_in()) {
            flash('err', 'Please log in to continue.');
            redirect('/auth/role.php');
        }
        if ($role && ($_SESSION['user']['role'] ?? null) !== $role) {
            http_response_code(403);
            echo "Forbidden: this page is only for $role accounts.";
            exit;
        }
    }

    public static function guestOnly(): void
    {
        if (is_logged_in()) {
            $role = $_SESSION['user']['role'] ?? '';
            if ($role === 'seller')      redirect('/frontend/pages/seller/dashboard.php');
            elseif ($role === 'admin')   redirect('/frontend/pages/admin/dashboard.php');
            else                         redirect('/frontend/pages/buyer/home.php');
        }
    }

    /* ---------------- API (JWT) -------------------- */

    /** Returns the authenticated user array.  Sends 401 + exits otherwise. */
    public static function requireApi(?string $role = null): array
    {
        $user = AuthService::userFromRequest();
        if (!$user) {
            Response::unauthorized('Missing or invalid token');
        }
        if ($role && $user['role'] !== $role) {
            Response::forbidden('Insufficient role');
        }
        return $user;
    }
}
