<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('buyer');

$user        = current_user();
$displayCur  = $user['currency'] ?? 'IDR';

$filters = [
    'category_id' => (int) ($_GET['cat'] ?? 0) ?: null,
    'search'      => trim((string) ($_GET['q'] ?? '')) ?: null,
    'sort'        => $_GET['sort'] ?? null,
    'limit'       => 24,
    'in_stock'    => true,
];
$products      = ProductModel::search($filters);
$categories    = ProductModel::categories();
$recommendations = RecommendationService::topPicks((int) $user['user_id'], 8);
$wishlistIds   = array_column(WishlistModel::listFor((int) $user['user_id']), 'product_id');

layout('header', ['title' => __('Discover')]);
?>
<?php component('flash'); ?>

<!-- Hero -->
<div class="card" style="background: linear-gradient(120deg, var(--color-primary), #ff8a65); color:#fff; margin-bottom:24px;">
    <h2 style="font-size:24px;"><?= e(__('Hi :name', ['name' => $user['name']])) ?></h2>
    <p style="opacity:.95;"><?= e(__('Find quality products from rated & trusted sellers.')) ?></p>
    <div class="row mt-2">
        <a class="btn" style="background:rgba(255,255,255,.18); color:#fff;" href="#categories"><?= e(__('Browse categories')) ?></a>
        <a class="btn" style="background:#fff; color:var(--color-primary);" href="<?= base_url('/frontend/pages/buyer/wishlist.php') ?>"><?= e(__('My wishlist')) ?></a>
    </div>
</div>

<!-- Recommendations -->
<?php if (!empty($recommendations)): ?>
<h3 class="mb-2"><?= e(__('Recommended for you')) ?></h3>
<div class="grid grid-cards mb-3">
    <?php foreach ($recommendations as $p):
        $p['_wished'] = in_array($p['product_id'], $wishlistIds, true);
        component('product_card', ['p' => $p, 'displayCurrency' => $displayCur]);
    endforeach; ?>
</div>
<?php endif; ?>

<!-- Categories -->
<h3 id="categories" class="mb-2"><?= e(__('Categories')) ?></h3>
<div class="row mb-3" style="gap:8px; flex-wrap:wrap;">
    <a class="tag <?= empty($filters['category_id']) ? 'tag-primary' : '' ?>" href="<?= base_url('/frontend/pages/buyer/home.php') ?>"><?= e(__('All')) ?></a>
    <?php foreach ($categories as $c): ?>
        <a class="tag <?= ($filters['category_id'] ?? 0) == $c['category_id'] ? 'tag-primary' : '' ?>"
           href="<?= base_url('/frontend/pages/buyer/home.php?cat=' . (int) $c['category_id']) ?>">
            <?= e($c['category_name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<!-- Filters bar -->
<div class="card card-tight mb-3" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
    <span class="text-muted fs-13"><?= e(__('Sort')) ?>:</span>
    <?php
    $sorts = [
        '' => __('Newest'),
        'popular' => __('Popular'),
        'rating' => __('Top rated'),
        'price_asc' => __('Price ascending'),
        'price_desc' => __('Price descending'),
    ];
    $params = $_GET;
    foreach ($sorts as $key => $label):
        $params['sort'] = $key;
        $href = base_url('/frontend/pages/buyer/home.php?' . http_build_query($params));
        $active = ($_GET['sort'] ?? '') === $key;
    ?>
        <a class="tag <?= $active ? 'tag-primary' : '' ?>" href="<?= $href ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<!-- Products -->
<h3 class="mb-2">
    <?= e(__('Products')) ?>
    <?php if (!empty($filters['search'])): ?>
        <small class="text-muted">- "<?= e($filters['search']) ?>"</small>
    <?php endif; ?>
</h3>

<?php if (empty($products)): ?>
    <div class="card center" style="padding: 50px; flex-direction: column;">
        <div style="font-size:24px; color:var(--text-soft);">No results</div>
        <p class="text-muted"><?= e(__('No products match your filters.')) ?></p>
    </div>
<?php else: ?>
<div class="grid grid-cards">
    <?php foreach ($products as $p):
        $p['_wished'] = in_array($p['product_id'], $wishlistIds, true);
        component('product_card', ['p' => $p, 'displayCurrency' => $displayCur]);
    endforeach; ?>
</div>
<?php endif; ?>

<?php layout('footer'); ?>
