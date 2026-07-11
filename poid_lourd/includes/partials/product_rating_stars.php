<?php
/**
 * Affichage étoiles produit (lecture seule).
 * Variables : $note (float 0-5), $count (int optionnel), $size (sm|md), $class_extra (string)
 */
$pr_note = isset($note) ? max(0.0, min(5.0, (float) $note)) : 0.0;
$pr_count = isset($count) ? max(0, (int) $count) : 0;
$pr_size = (isset($size) && $size === 'sm') ? 'pr-stars--sm' : '';
$pr_extra = isset($class_extra) ? trim((string) $class_extra) : '';
if ($pr_count <= 0 && $pr_note <= 0) {
    return;
}
$pr_pct = max(0, min(100, ($pr_note / 5) * 100));
$pr_label = number_format($pr_note, 1, ',', ' ') . ' sur 5';
if ($pr_count > 0) {
    $pr_label .= ' (' . $pr_count . ' avis)';
}
?>
<span class="pr-stars pr-stars--readonly <?php echo htmlspecialchars($pr_size . ' ' . $pr_extra, ENT_QUOTES, 'UTF-8'); ?>"
    style="--pr-rating: <?php echo round($pr_pct, 2); ?>;"
    role="img"
    aria-label="<?php echo htmlspecialchars($pr_label, ENT_QUOTES, 'UTF-8'); ?>">
    <span class="pr-stars__track" aria-hidden="true">
        <span class="pr-stars__empty"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></span>
        <span class="pr-stars__fill"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></span>
    </span>
    <?php if ($pr_count > 0): ?>
    <span class="pr-stars__count"><?php echo number_format($pr_note, 1, ',', ' '); ?></span>
    <?php endif; ?>
</span>
