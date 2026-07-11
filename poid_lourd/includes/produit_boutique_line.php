<?php
/**
 * Ligne « boutique » sous le nom sur les cartes produit (marketplace uniquement).
 */
require_once __DIR__ . '/marketplace_helpers.php';

function produit_card_boutique_line_html(array $produit) {
    if (defined('BOUTIQUE_SLUG') && BOUTIQUE_SLUG !== '') {
        return '';
    }
    $label = produit_public_boutique_label($produit);
    $slug = trim((string) ($produit['vendeur_boutique_slug'] ?? ''));
    $out = '<p class="produit-card-boutique">';
    if ($slug !== '') {
        $out .= '<a href="' . htmlspecialchars(boutique_vitrine_entry_href($slug), ENT_QUOTES, 'UTF-8') . '" class="produit-card-boutique-link">';
        $out .= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    } else {
        $out .= '<span class="produit-card-boutique-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $out .= '</p>';
    return $out;
}
