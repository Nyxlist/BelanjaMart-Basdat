<?php
/**
 * PricingService - dynamic pricing helper for sellers.
 *
 * A full implementation would scrape competitor sites; for now we let
 * the seller paste a few sample prices and we compute fair-market
 * suggestions, profit estimates and convert across currencies.
 */

class PricingService
{
    /**
     * Suggest a price range for the seller's market.
     *
     * @param array<int,array{source:string,price:float,currency:string}> $samples
     * @param string $targetCurrency  the seller's product currency
     * @param string $countryCode     destination country (for tax + shipping)
     * @param float  $costPerUnit     seller's cost (in target currency)
     * @return array
     */
    public static function suggest(array $samples, string $targetCurrency, string $countryCode, float $costPerUnit = 0): array
    {
        if (empty($samples)) {
            return [
                'min' => 0, 'max' => 0, 'avg' => 0,
                'suggested' => 0, 'breakdown' => [],
                'message' => 'Provide at least one comparison price.',
            ];
        }

        $country = Database::one("SELECT * FROM countries WHERE country_code = ?", [$countryCode]);
        $taxRate = (float) ($country['tax_rate']      ?? 0);
        $shipFee = (float) ($country['base_shipping'] ?? 0);

        $converted = [];
        foreach ($samples as $s) {
            $converted[] = [
                'source'    => $s['source'],
                'original'  => (float) $s['price'],
                'currency'  => $s['currency'],
                'in_target' => Currency::convert((float) $s['price'], $s['currency'], $targetCurrency),
            ];
        }
        $values = array_column($converted, 'in_target');
        sort($values);

        $min = min($values);
        $max = max($values);
        $avg = array_sum($values) / count($values);

        // Suggested = average + tax + shipping baseline, rounded
        $suggested = $avg + ($avg * $taxRate) + $shipFee;

        $profit = $costPerUnit > 0
            ? [
                'cost'        => $costPerUnit,
                'profit_avg'  => $avg - $costPerUnit,
                'profit_pct'  => $costPerUnit > 0 ? round(($avg - $costPerUnit) / $costPerUnit * 100, 2) : null,
            ]
            : null;

        return [
            'min'        => round($min, 2),
            'max'        => round($max, 2),
            'avg'        => round($avg, 2),
            'suggested'  => round($suggested, 2),
            'currency'   => $targetCurrency,
            'tax_rate'   => $taxRate,
            'shipping'   => $shipFee,
            'samples'    => $converted,
            'profit'     => $profit,
            'message'    => 'Suggested price uses regional tax + base shipping.',
        ];
    }

    /** Persist the snapshot to the price_suggestions table. */
    public static function record(int $productId, array $samples, string $targetCurrency): void
    {
        foreach ($samples as $s) {
            Database::insert('price_suggestions', [
                'product_id'      => $productId,
                'source'          => $s['source'],
                'source_price'    => $s['price'],
                'source_currency' => $s['currency'],
                'converted_price' => Currency::convert((float) $s['price'], $s['currency'], $targetCurrency),
            ]);
        }
    }
}
