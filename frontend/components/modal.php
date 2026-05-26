<?php
/**
 * Generic modal shell.
 *   component('modal', ['id' => 'cancelModal', 'title' => 'Cancel', 'body' => '...'])
 *
 * If you need rich content inside the body, render it before this and
 * pass the HTML in via $body, OR open <div id="...">...</div> manually.
 */
?>
<div class="modal-backdrop" id="<?= e($id ?? 'modal') ?>">
    <div class="modal" role="dialog" aria-modal="true">
        <div class="modal-header">
            <h3><?= e($title ?? 'Modal') ?></h3>
            <button class="btn btn-ghost" data-modal-close>✕</button>
        </div>
        <div class="modal-body"><?= $body ?? '' ?></div>
        <?php if (!empty($footer)): ?>
        <div class="modal-footer"><?= $footer ?></div>
        <?php endif; ?>
    </div>
</div>
