<?php
/**
 * Modal partage unifiée (produits catalogue + boutique vendeur).
 * Remplie dynamiquement par js/platform-share-modal.js
 */
if (!function_exists('asset_version_query')) {
    require_once __DIR__ . '/../asset_version.php';
}
?>
<div class="prm-share-modal" id="platformShareModal" role="dialog"
    aria-modal="true" aria-labelledby="platformShareModalTitle" aria-hidden="true" hidden>
    <div class="prm-share-modal__backdrop" data-share-close tabindex="-1"></div>
    <div class="prm-share-modal__panel" role="document">
        <div class="prm-share-modal__head">
            <h2 class="prm-share-modal__title" id="platformShareModalTitle">Partager</h2>
            <button type="button" class="prm-share-modal__close" data-share-close aria-label="Fermer">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <div class="prm-share-modal__url-row">
            <p class="prm-share-modal__url" id="platformShareModalUrl"></p>
            <button type="button" class="prm-share-modal__url-copy" id="platformShareModalUrlCopy"
                aria-label="Copier le lien" title="Copier le lien">
                <span class="prm-share-modal__url-copy-label">Copier</span>
                <i class="fas fa-link" aria-hidden="true"></i>
            </button>
        </div>
        <div class="prm-share-grid">
            <button type="button" class="prm-share-item prm-share-item--wa" data-share-channel="wa">
                <span class="prm-share-item__icon"><i class="fab fa-whatsapp" aria-hidden="true"></i></span>
                <span>WhatsApp</span>
            </button>
            <button type="button" class="prm-share-item prm-share-item--gmail" data-share-channel="gmail">
                <span class="prm-share-item__icon"><i class="fab fa-google" aria-hidden="true"></i></span>
                <span>Gmail</span>
            </button>
            <button type="button" class="prm-share-item prm-share-item--fb" data-share-channel="fb">
                <span class="prm-share-item__icon"><i class="fab fa-facebook-f" aria-hidden="true"></i></span>
                <span>Facebook</span>
            </button>
            <button type="button" class="prm-share-item prm-share-item--tg" data-share-channel="tg">
                <span class="prm-share-item__icon"><i class="fab fa-telegram-plane" aria-hidden="true"></i></span>
                <span>Telegram</span>
            </button>
        </div>
        <p class="prm-share-modal__hint" id="platformShareModalHint" hidden></p>
    </div>
</div>
