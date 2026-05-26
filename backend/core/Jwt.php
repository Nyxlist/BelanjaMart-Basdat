<?php
/**
 * Minimal HS256 JWT implementation - no external dependency.
 *
 * Educational only.  Production code should use firebase/php-jwt or
 * lcobucci/jwt which handle clock skew, key rotation, etc.
 */

class Jwt
{
    public static function encode(array $payload, ?string $secret = null, int $ttl = null): string
    {
        $cfg     = config('jwt');
        $secret  = $secret ?? $cfg['secret'];
        $ttl     = $ttl    ?? $cfg['ttl_seconds'];

        $now = time();
        $payload = array_merge([
            'iss' => $cfg['issuer'],
            'iat' => $now,
            'exp' => $now + $ttl,
        ], $payload);

        $header  = self::b64(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body    = self::b64(json_encode($payload));
        $sig     = self::b64(hash_hmac('sha256', "$header.$body", $secret, true));

        return "$header.$body.$sig";
    }

    /** Decode + verify.  Returns payload array or null on failure. */
    public static function decode(string $token, ?string $secret = null): ?array
    {
        $secret = $secret ?? config('jwt.secret');
        $parts  = explode('.', $token);
        if (count($parts) !== 3) return null;

        [$header, $body, $sig] = $parts;
        $expected = self::b64(hash_hmac('sha256', "$header.$body", $secret, true));
        if (!hash_equals($expected, $sig)) return null;

        $payload = json_decode(self::b64decode($body), true);
        if (!is_array($payload)) return null;

        if (isset($payload['exp']) && $payload['exp'] < time()) return null;
        return $payload;
    }

    /** Pull a token from `Authorization: Bearer <jwt>` header. */
    public static function fromAuthHeader(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if (!$auth && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
        }
        if (!$auth || stripos($auth, 'Bearer ') !== 0) return null;
        return trim(substr($auth, 7));
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64decode(string $b64): string
    {
        $pad = strlen($b64) % 4;
        if ($pad) $b64 .= str_repeat('=', 4 - $pad);
        return base64_decode(strtr($b64, '-_', '+/'));
    }
}
