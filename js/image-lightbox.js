/**
 * Lightbox plein écran pour images produit
 */
(function () {
    'use strict';

    var lightbox = null;
    var lightboxImg = null;
    var lightboxCaption = null;
    var lastFocus = null;

    function ensureLightbox() {
        if (lightbox) {
            return;
        }
        lightbox = document.createElement('div');
        lightbox.id = 'sugar-image-lightbox';
        lightbox.className = 'sugar-image-lightbox';
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.setAttribute('aria-hidden', 'true');
        lightbox.innerHTML =
            '<div class="sugar-image-lightbox__dialog">' +
            '<button type="button" class="sugar-image-lightbox__close" aria-label="Fermer">&times;</button>' +
            '<img class="sugar-image-lightbox__img" alt="" src="">' +
            '<p class="sugar-image-lightbox__caption" hidden></p>' +
            '</div>';
        document.body.appendChild(lightbox);

        lightboxImg = lightbox.querySelector('.sugar-image-lightbox__img');
        lightboxCaption = lightbox.querySelector('.sugar-image-lightbox__caption');
        var closeBtn = lightbox.querySelector('.sugar-image-lightbox__close');

        closeBtn.addEventListener('click', close);
        lightbox.addEventListener('click', function (event) {
            if (event.target === lightbox) {
                close();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && lightbox.classList.contains('is-open')) {
                close();
            }
        });
    }

    function open(src, alt, caption) {
        if (!src) {
            return;
        }
        ensureLightbox();
        lastFocus = document.activeElement;
        lightboxImg.src = src;
        lightboxImg.alt = alt || '';
        if (caption) {
            lightboxCaption.textContent = caption;
            lightboxCaption.hidden = false;
        } else {
            lightboxCaption.textContent = '';
            lightboxCaption.hidden = true;
        }
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        lightbox.querySelector('.sugar-image-lightbox__close').focus();
    }

    function close() {
        if (!lightbox) {
            return;
        }
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        lightboxImg.src = '';
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    function bindTrigger(el) {
        if (!el || el.dataset.lightboxBound === '1') {
            return;
        }
        el.dataset.lightboxBound = '1';
        var src = el.getAttribute('data-lightbox-src') || el.getAttribute('src') || '';
        var alt = el.getAttribute('data-lightbox-alt') || el.getAttribute('alt') || '';
        var caption = el.getAttribute('data-lightbox-caption') || '';

        function handleOpen(event) {
            event.preventDefault();
            event.stopPropagation();
            var currentSrc = el.getAttribute('data-lightbox-src') || el.getAttribute('src') || src;
            open(currentSrc, alt, caption);
        }

        el.addEventListener('click', handleOpen);
        el.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                handleOpen(event);
            }
        });
    }

    function bindAll(selector, root) {
        var scope = root || document;
        scope.querySelectorAll(selector).forEach(bindTrigger);
    }

    window.SugarImageLightbox = {
        open: open,
        close: close,
        bind: bindTrigger,
        bindAll: bindAll
    };
})();
