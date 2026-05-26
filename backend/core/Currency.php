<?php
/**
 * Currency helper - reads exchange rates from the `currencies` table.
 * Rates are stored as "1 USD = N <code>", so:
 *
 *   amount_in_USD  = amount / rate(code)
 *   amount_in_DEST = amount_in_USD * rate(DEST)
 */

class Currency
{
    private static array $cache = [];

    public static function load(): array
    {
        if (!empty(self::$cache)) return self::$cache;
        $rows = Database::all("SELECT * FROM currencies");
        foreach ($rows as $r) self::$cache[$r['currency_code']] = $r;
        return self::$cache;
    }

    public static function symbol(string $code): string
    {
        $all = self::load();
        return $all[$code]['symbol'] ?? $code;
    }

    public static function rate(string $code): float
    {
        $all = self::load();
        return (float) ($all[$code]['rate_to_usd'] ?? 1);
    }

    public static function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) return $amount;
        $usd = $amount / max(self::rate($from), 0.0000001);
        return $usd * self::rate($to);
    }

    /** Pretty-print an amount using the currency's symbol. */
    public static function format(float $amount, string $code): string
    {
        $sym = self::symbol($code);
        // IDR & JPY usually shown without decimals
        $decimals = in_array($code, ['IDR', 'JPY'], true) ? 0 : 2;
        return $sym . ' ' . number_format($amount, $decimals, ',', '.');
    }

    /** Convert and format in one call. */
    public static function display(float $amount, string $from, string $to): string
    {
        return self::format(self::convert($amount, $from, $to), $to);
    }

    public static function all(): array
    {
        return self::load();
    }
}
