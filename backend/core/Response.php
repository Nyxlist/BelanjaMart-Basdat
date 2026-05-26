<?php
/**
 * Standard JSON response envelope used by the REST API.
 * Format:  { "success": bool, "data": ..., "error": "...", "meta": {...} }
 */

class Response
{
    public static function ok($data = null, array $meta = []): void
    {
        $payload = ['success' => true];
        if ($data !== null) $payload['data'] = $data;
        if (!empty($meta))  $payload['meta'] = $meta;
        json_response($payload, 200);
    }

    public static function created($data = null): void
    {
        json_response(['success' => true, 'data' => $data], 201);
    }

    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        json_response(array_merge([
            'success' => false,
            'error'   => $message,
        ], $extra), $status);
    }

    public static function unauthorized(string $msg = 'Unauthorized'): void
    {
        self::error($msg, 401);
    }

    public static function forbidden(string $msg = 'Forbidden'): void
    {
        self::error($msg, 403);
    }

    public static function notFound(string $msg = 'Not found'): void
    {
        self::error($msg, 404);
    }

    public static function validation(array $errors): void
    {
        json_response([
            'success' => false,
            'error'   => 'Validation failed',
            'errors'  => $errors,
        ], 422);
    }
}
