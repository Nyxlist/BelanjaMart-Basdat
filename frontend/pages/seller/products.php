<?php
require_once __DIR__ . '/../../../backend/config/bootstrap.php';
AuthMiddleware::requireWeb('seller');

$user       = current_user();
$sellerId   = (int) $user['user_id'];
$categories = ProductModel::categories();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check()) { flash('err', 'Session expired, please try again.'); redirect('/frontend/pages/seller/products.php'); }

    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $v = new Validator($_POST, [
            'product_name' => ['required', 'min:2', 'max:180'],
            'price'        => ['required', 'numeric'],
            'stock'        => ['required', 'numeric'],
            'category_id'  => ['required', 'numeric'],
        ]);
        if ($v->fails()) {
            flash('err', $v->first());
        } else {
            ProductModel::create($sellerId, $_POST + [
                'currency_code' => $user['currency'] ?? 'IDR',
            ]);
            flash('ok', 'Product created.');
        }
    } elseif ($action === 'update') {
        ProductModel::update((int) $_POST['product_id'], $sellerId, $_POST);
        flash('ok', 'Product updated.');
    } elseif ($action === 'delete') {
        ProductModel::delete((int) ($_POST['product_id'] ?? 0), $sellerId);
        flash('ok', 'Product deleted.');
    }
    redirect('/frontend/pages/seller/products.php');
}

$products = ProductModel::bySeller($sellerId);
$edit     = null;
if (isset($_GET['edit'])) {
    foreach ($products as $p) if ((int) $p['product_id'] === (int) $_GET['edit']) $edit = $p;
}

layout('header', ['title' => 'Products']);
?>
<?php component('flash'); ?>
<div class="layout">
    <?php component('sidebar_seller'); ?>
    <div>
        <!-- Form -->
        <div class="card">
            <h3><?= $edit ? 'Edit product' : 'Add product' ?></h3>
            <form method="POST" class="grid" style="grid-template-columns: repeat(2, 1fr); gap:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
                <?php if ($edit): ?><input type="hidden" name="product_id" value="<?= (int) $edit['product_id'] ?>"><?php endif; ?>

                <div class="form-row" style="grid-column: span 2;">
                    <label>Product name</label>
                    <input type="text" name="product_name" required value="<?= e($edit['product_name'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Price (<?= e($user['currency'] ?? 'IDR') ?>)</label>
                    <input type="number" name="price" min="0" step="0.01" required value="<?= e($edit['price'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Stock</label>
                    <input type="number" name="stock" min="0" required value="<?= e($edit['stock'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <label>Category</label>
                    <select name="category_id" required>
                        <option value="">-- pick category --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['category_id'] ?>" <?= ($edit['category_id'] ?? null) == $c['category_id'] ? 'selected' : '' ?>>
                                <?= e($c['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Weight (g)</label>
                    <input type="number" name="weight_grams" min="0" value="<?= e($edit['weight_grams'] ?? 500) ?>">
                </div>
                <div class="form-row" style="grid-column: span 2;">
                    <label>Description</label>
                    <textarea name="description"><?= e($edit['description'] ?? '') ?></textarea>
                </div>
                <div class="row" style="grid-column: span 2; gap:8px;">
                    <button class="btn btn-primary" type="submit"><?= $edit ? 'Save changes' : 'Add product' ?></button>
                    <?php if ($edit): ?>
                        <a class="btn btn-outline" href="<?= base_url('/frontend/pages/seller/products.php') ?>">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Listing -->
        <div class="card mt-3" style="overflow-x:auto;">
            <h3>My products</h3>
            <?php if (empty($products)): ?>
                <p class="text-muted">No products yet.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Sold</th><th>Rating</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr>
                            <td><?= e($p['product_name']) ?></td>
                            <td><?= e($p['category_name'] ?? '-') ?></td>
                            <td><?= Currency::format((float) $p['price'], $p['currency_code']) ?></td>
                            <td><?= (int) $p['stock'] ?></td>
                            <td><?= (int) $p['total_sold'] ?></td>
                            <td>★ <?= number_format((float) $p['average_rating'], 1) ?></td>
                            <td>
                                <a class="btn btn-info btn-sm" href="?edit=<?= (int) $p['product_id'] ?>">Edit</a>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php layout('footer'); ?>
