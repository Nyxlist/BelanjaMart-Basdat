<?php
$ok  = flash('ok');
$err = flash('err');
if ($ok):
?>
<div class="toast toast-success" style="position:relative; margin-bottom:12px;">
    <?= e($ok) ?>
</div>
<?php endif; if ($err): ?>
<div class="toast toast-danger" style="position:relative; margin-bottom:12px;">
    <?= e($err) ?>
</div>
<?php endif; ?>
