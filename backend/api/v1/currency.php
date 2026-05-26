<?php
/**
 * GET /api/v1/?route=currency&from=USD&to=IDR&amount=10
 *   - if `from`, `to` and `amount` are given, return converted value.
 *   - otherwise return the full table of currencies + rates.
 */
$from   = $_GET['from']   ?? null;
$to     = $_GET['to']     ?? null;
$amount = $_GET['amount'] ?? null;

if ($from && $to && $amount !== null) {
    Response::ok([
        'from'      => $from,
        'to'        => $to,
        'amount'    => (float) $amount,
        'converted' => round(Currency::convert((float) $amount, $from, $to), 4),
        'formatted' => Currency::display((float) $amount, $from, $to),
    ]);
}
Response::ok(Currency::all());
