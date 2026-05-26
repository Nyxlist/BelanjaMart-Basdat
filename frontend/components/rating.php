<?php
/** Quick star rating display. */
$value = (float) ($value ?? 0);
$count = (int) ($count ?? 0);
$full  = floor($value);
$half  = ($value - $full) >= 0.5;
?>
<span style="white-space:nowrap;">
    <?php for ($i = 1; $i <= 5; $i++): ?>
        <?php if ($i <= $full): ?>★<?php elseif ($i == $full + 1 && $half): ?>☆<?php else: ?>·<?php endif; ?>
    <?php endfor; ?>
    <small class="text-muted"><?= number_format($value, 1) ?> (<?= $count ?>)</small>
</span>
