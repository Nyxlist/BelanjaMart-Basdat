<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('seller');

$user      = current_user();
$result    = null;
$products  = ProductModel::bySeller((int) $user['user_id']);
$currencies= Database::all("SELECT * FROM currencies ORDER BY currency_code");
$countries = Database::all("SELECT * FROM countries ORDER BY country_name");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) { flash('err', 'Session expired, please try again.'); redirect('/frontend/pages/seller/pricing.php'); }

    $samples = [];
    foreach ($_POST['source'] ?? [] as $i => $src) {
        $price = (float) ($_POST['price'][$i] ?? 0);
        if ($price <= 0 || empty($src)) continue;
        $samples[] = [
            'source'   => trim($src),
            'price'    => $price,
            'currency' => $_POST['currency'][$i] ?? 'USD',
        ];
    }

    $targetCur = $_POST['target_currency'] ?? ($user['currency'] ?? 'IDR');
    $country   = $_POST['country_code']    ?? ($user['country']  ?? 'ID');
    $cost      = (float) ($_POST['cost']  ?? 0);

    $result = PricingService::suggest($samples, $targetCur, $country, $cost);

    if (!empty($_POST['save_to']) && (int) $_POST['save_to'] > 0) {
        PricingService::record((int) $_POST['save_to'], $samples, $targetCur);
        flash('ok', 'Snapshot saved to product history.');
    }
}

layout('header', ['title' => 'Smart Pricing']);
?>
<?php component('flash'); ?>
<div class="layout">
    <?php component('sidebar_seller'); ?>
    <div>
        <h2>Smart Pricing Tool</h2>
        <p class="text-muted">Compare competitor prices, convert across currencies and get a suggested price (incl. tax + shipping) for your market.</p>

        <div class="card mt-2">
            <form method="POST" id="priceForm">
                <?= csrf_field() ?>
                <h3>Comparison prices</h3>
                <p class="text-muted fs-13">Add up to 5 reference prices.  Foreign currencies are auto-converted.</p>
                <div id="rows">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                        <div class="row" style="gap:6px; margin-bottom:6px;">
                            <input type="text" name="source[]"   class="input" placeholder="Source (e.g. Tokopedia)" style="flex:1.4;">
                            <input type="number" name="price[]"  class="input" placeholder="Price" step="0.01" style="flex:1;">
                            <select name="currency[]" class="select" style="flex:.7;">
                                <?php foreach ($currencies as $c): ?>
                                    <option value="<?= e($c['currency_code']) ?>"><?= e($c['currency_code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="grid" style="grid-template-columns: repeat(4,1fr); gap:8px;">
                    <div class="form-row">
                        <label>Your currency</label>
                        <select name="target_currency">
                            <?php foreach ($currencies as $c): ?>
                                <option value="<?= e($c['currency_code']) ?>" <?= ($c['currency_code'] === ($user['currency'] ?? 'IDR')) ? 'selected' : '' ?>>
                                    <?= e($c['currency_code']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Target country</label>
                        <select name="country_code">
                            <?php foreach ($countries as $c): ?>
                                <option value="<?= e($c['country_code']) ?>" <?= ($c['country_code'] === ($user['country'] ?? 'ID')) ? 'selected' : '' ?>>
                                    <?= e($c['country_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>Your cost / unit</label>
                        <input type="number" name="cost" step="0.01" placeholder="(optional)">
                    </div>
                    <div class="form-row">
                        <label>Save snapshot to</label>
                        <select name="save_to">
                            <option value="">— don't save —</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int) $p['product_id'] ?>"><?= e($p['product_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary mt-2">Compute suggestion</button>
            </form>
        </div>

        <?php if ($result): ?>
        <div class="card mt-3">
            <h3>Suggestion</h3>
            <?php if (empty($result['samples'])): ?>
                <p class="text-muted"><?= e($result['message']) ?></p>
            <?php else: ?>
                <div class="grid" style="grid-template-columns: repeat(4,1fr); gap:8px;">
                    <div class="card card-tight"><div class="text-muted fs-13">Min</div><div class="fw-bold"><?= Currency::format($result['min'], $result['currency']) ?></div></div>
                    <div class="card card-tight"><div class="text-muted fs-13">Avg</div><div class="fw-bold"><?= Currency::format($result['avg'], $result['currency']) ?></div></div>
                    <div class="card card-tight"><div class="text-muted fs-13">Max</div><div class="fw-bold"><?= Currency::format($result['max'], $result['currency']) ?></div></div>
                    <div class="card card-tight" style="background:rgba(192,57,43,.1);">
                        <div class="text-muted fs-13">Suggested price (incl. tax + shipping)</div>
                        <div class="fw-bold" style="color:var(--color-primary);"><?= Currency::format($result['suggested'], $result['currency']) ?></div>
                    </div>
                </div>
                <div class="text-muted fs-13 mt-2">
                    Includes regional tax (<?= number_format($result['tax_rate'] * 100, 1) ?>%) and base shipping <?= Currency::format($result['shipping'], $result['currency']) ?>.
                </div>

                <?php if (!empty($result['profit'])): ?>
                    <div class="divider"></div>
                    <h3>Profit estimate</h3>
                    <div class="row" style="gap:24px; flex-wrap:wrap;">
                        <div><div class="text-muted fs-13">Cost / unit</div><div class="fw-bold"><?= Currency::format($result['profit']['cost'], $result['currency']) ?></div></div>
                        <div><div class="text-muted fs-13">Avg profit</div><div class="fw-bold text-success"><?= Currency::format($result['profit']['profit_avg'], $result['currency']) ?></div></div>
                        <?php if (isset($result['profit']['profit_pct'])): ?>
                        <div><div class="text-muted fs-13">Margin</div><div class="fw-bold"><?= number_format($result['profit']['profit_pct'], 2) ?>%</div></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <h3 class="mt-3">Sources</h3>
                <div style="overflow-x:auto;">
                    <table class="table" style="table-layout:fixed; width:100%;">
                        <thead><tr><th style="width:60%;">Source</th><th style="width:20%;">Original</th><th style="width:20%;">In <?= e($result['currency']) ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($result['samples'] as $s): ?>
                                <tr>
                                    <td style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:0;"><?= e($s['source']) ?></td>
                                    <td><?= Currency::format((float) $s['original'], $s['currency']) ?></td>
                                    <td><?= Currency::format((float) $s['in_target'], $result['currency']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php layout('footer'); ?>
