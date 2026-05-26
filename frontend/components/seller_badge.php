<?php
/**
 * Seller badge pill list.
 *   component('seller_badge', ['badges' => $rows])
 */
$badges = $badges ?? [];
?>
<div class="row" style="gap:6px;">
    <?php foreach ($badges as $b): ?>
        <span class="tag" style="background: <?= e($b['color']) ?>22; color: <?= e($b['color']) ?>;">
            <?= e($b['icon']) ?> <?= e($b['label']) ?>
        </span>
    <?php endforeach; ?>
</div>
