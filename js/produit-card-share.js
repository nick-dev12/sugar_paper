/**
 * Bouton partage produit pour cartes injectées en JavaScript.
 */
(function () {
    function escapeHtml(str) {
        if (str === null || str === undefined) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatPrice(value) {
        var num = Number(value);
        if (!isFinite(num)) {
            num = 0;
        }
        return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' FCFA';
    }

    window.buildProduitShareButtonHtml = function (produit) {
        if (!produit || !produit.id) {
            return '';
        }

        var title = produit.share_title || produit.nom || 'Produit';
        var url = produit.share_url || ('/produit.php?id=' + produit.id);
        var text = produit.share_text;

        if (!text) {
            var prix = Number(produit.prix_affichage);
            if (!isFinite(prix) || prix <= 0) {
                var promo = Number(produit.prix_promotion);
                var base = Number(produit.prix);
                prix = (promo > 0 && base > 0 && promo < base) ? promo : base;
            }
            text = 'Découvrez « ' + title + ' » à ' + formatPrice(prix) + ' sur Sugar Paper.';
        }

        return ''
            + '<button type="button"'
            + ' class="produit-card-share js-platform-share"'
            + ' aria-label="Partager ' + escapeHtml(title) + '"'
            + ' data-share-modal-title="Partager le produit"'
            + ' data-share-title="' + escapeHtml(title) + '"'
            + ' data-share-url="' + escapeHtml(url) + '"'
            + ' data-share-text="' + escapeHtml(text) + '"'
            + ' data-share-hint="Partagez ce lien pour que vos clients consultent le produit.">'
            + '<i class="fa-solid fa-share-nodes" aria-hidden="true"></i>'
            + '</button>';
    };
})();
