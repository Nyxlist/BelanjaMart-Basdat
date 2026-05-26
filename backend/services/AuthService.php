<?php
/**
 * AuthService - registration, login, logout for both web sessions
 * and the JWT-based REST API.
 */

class AuthService
{
    /* ----------------------------------------------------------------
     | Registration
     |----------------------------------------------------------------*/

    /**
     * @return array{ok:bool, message?:string, user_id?:int}
     */
    public static function register(array $data, string $role): array
    {
        $v = new Validator($data, [
            'name'     => ['required', 'min:2', 'max:100'],
            'email'    => ['required', 'email', 'max:150'],
            'password' => ['required', 'min:6'],
        ]);
        if ($v->fails()) {
            return ['ok' => false, 'message' => $v->first()];
        }
        if (!in_array($role, ['buyer', 'seller'], true)) {
            return ['ok' => false, 'message' => 'Invalid role.'];
        }
        if (UserModel::emailExists($data['email'])) {
            return ['ok' => false, 'message' => 'Email already registered.'];
        }

        $userId = UserModel::create([
            'name'      => trim($data['name']),
            'email'     => strtolower(trim($data['email'])),
            'password'  => $data['password'],
            'role'      => $role,
            'shop_name' => $data['shop_name'] ?? null,
        ]);

        logger("Registered user #$userId as $role");
        return ['ok' => true, 'user_id' => $userId];
    }

    /* ----------------------------------------------------------------
     | Login
     |----------------------------------------------------------------*/

    /**
     * Verify credentials and return user row, or null on failure.
     * @param string|null $expectedRole if set, also checks role match.
     */
    public static function attempt(string $email, string $password, ?string $expectedRole = null): ?array
    {
        $user = UserModel::findByEmail(strtolower(trim($email)));
        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }
        if ($expectedRole && $user['role'] !== $expectedRole) {
            return null;
        }
        if (empty($user['is_active'])) {
            return null;
        }
        UserModel::touchLogin((int) $user['user_id']);
        return $user;
    }

    /** Persist the logged-in user into the PHP session. */
    public static function loginSession(array $user): void
    {
        $_SESSION['user'] = [
            'user_id'  => (int) $user['user_id'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'role'     => $user['role'],
            'avatar'   => $user['avatar'],
            'currency' => $user['preferred_currency'] ?? 'IDR',
            'country'  => $user['country_code']       ?? 'ID',
        ];

        // Backward-compat with the old code that read these directly.
        $_SESSION['user_id']   = (int) $user['user_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['role']      = $user['role'];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /* ----------------------------------------------------------------
     | API tokens
     |----------------------------------------------------------------*/

    public static function issueToken(array $user): string
    {
        return Jwt::encode([
            'sub'  => (int) $user['user_id'],
            'role' => $user['role'],
            'name' => $user['name'],
        ]);
    }

    /** Read + verify the JWT from the Authorization header. */
    public static function userFromRequest(): ?array
    {
        $token = Jwt::fromAuthHeader();
        if (!$token) return null;
        $payload = Jwt::decode($token);
        if (!$payload) return null;
        return UserModel::findById((int) $payload['sub']);
    }
}
