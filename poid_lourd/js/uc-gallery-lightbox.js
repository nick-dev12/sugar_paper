(function () {
    var lb = document.getElementById('ucGalleryLightbox');
    if (!lb) return;

    var backdrop = document.getElementById('ucGalleryLightboxBackdrop');
    var btnClose = document.getElementById('ucGalleryLightboxClose');
    var btnPrev = document.getElementById('ucGalleryLightboxPrev');
    var btnNext = document.getElementById('ucGalleryLightboxNext');
    var imgEl = document.getElementById('ucGalleryLightboxImg');
    var titleEl = document.getElementById('ucGalleryLightboxTitle');
    var thumbsEl = document.getElementById('ucGalleryLightboxThumbs');
    var urls = [];
    var index = 0;

    function renderThumbs() {
        if (!thumbsEl) return;
        thumbsEl.innerHTML = '';
        if (urls.length <= 1) {
            thumbsEl.hidden = true;
            return;
        }
        thumbsEl.hidden = false;
        urls.forEach(function (url, i) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = i === index ? 'is-active' : '';
            btn.setAttribute('aria-label', 'Image ' + (i + 1));
            var thumbImg = document.createElement('img');
            thumbImg.src = url;
            thumbImg.alt = '';
            thumbImg.loading = 'lazy';
            btn.appendChild(thumbImg);
            btn.addEventListener('click', function () {
                index = i;
                updateView();
            });
            thumbsEl.appendChild(btn);
        });
    }

    function updateView() {
        if (!urls.length || !imgEl) return;
        imgEl.src = urls[index];
        imgEl.alt = titleEl ? titleEl.textContent : '';
        if (btnPrev) btnPrev.disabled = index <= 0;
        if (btnNext) btnNext.disabled = index >= urls.length - 1;
        if (thumbsEl) {
            thumbsEl.querySelectorAll('button').forEach(function (b, i) {
                b.classList.toggle('is-active', i === index);
            });
        }
    }

    function closeLb() {
        lb.hidden = true;
        lb.setAttribute('aria-hidden', 'true');
        urls = [];
        index = 0;
        if (imgEl) imgEl.src = '';
    }

    function openLb(list, title) {
        if (!Array.isArray(list) || !list.length) return;
        urls = list.slice();
        index = 0;
        if (titleEl) titleEl.textContent = title || '';
        renderThumbs();
        updateView();
        lb.hidden = false;
        lb.setAttribute('aria-hidden', 'false');
        if (btnClose) btnClose.focus();
    }

    document.querySelectorAll('.uc-btn-open-gallery').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var raw = btn.getAttribute('data-gallery') || '[]';
            var title = btn.getAttribute('data-gallery-title') || '';
            try {
                openLb(JSON.parse(raw), title);
            } catch (err) {
                /* ignore */
            }
        });
    });

    if (btnClose) btnClose.addEventListener('click', closeLb);
    if (backdrop) backdrop.addEventListener('click', closeLb);
    if (btnPrev) btnPrev.addEventListener('click', function () {
        if (index > 0) { index--; updateView(); }
    });
    if (btnNext) btnNext.addEventListener('click', function () {
        if (index < urls.length - 1) { index++; updateView(); }
    });

    document.addEventListener('keydown', function (e) {
        if (lb.hidden) return;
        if (e.key === 'Escape') closeLb();
        if (e.key === 'ArrowLeft' && index > 0) { index--; updateView(); }
        if (e.key === 'ArrowRight' && index < urls.length - 1) { index++; updateView(); }
    });
})();
