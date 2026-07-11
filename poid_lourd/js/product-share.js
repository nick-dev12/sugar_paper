/**
 * Partage produit — délégué à platform-share-modal.js (conservé pour compatibilité des includes).
 */
(function () {
    'use strict';
    if (typeof window.buildProductShareHtml === 'function') {
        return;
    }
    window.buildProductShareHtml = function () {
        return '';
    };
})();
