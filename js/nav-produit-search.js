/**
 * Suggestions temps réel — barre de recherche nav (nom + vignette).
 */
(function () {
    'use strict';

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function init() {
        var input = document.getElementById('nav-search');
        var form = document.getElementById('nav-search-form');
        var wrapper = input ? input.closest('.nav-search-wrapper') : null;
        if (!input || !form || !wrapper) {
            return;
        }

        var list = document.getElementById('nav-search-suggest');
        if (!list) {
            list = document.createElement('ul');
            list.id = 'nav-search-suggest';
            list.className = 'nav-search-suggest';
            list.setAttribute('role', 'listbox');
            list.setAttribute('aria-label', 'Suggestions produits');
            list.hidden = true;
            wrapper.appendChild(list);
        }

        var timer = null;
        var abort = null;
        var activeIndex = -1;

        function hideSuggest() {
            list.innerHTML = '';
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        }

        function setActive(idx) {
            var items = list.querySelectorAll('.nav-search-suggest__item');
            for (var i = 0; i < items.length; i++) {
                items[i].classList.toggle('is-active', i === idx);
            }
            activeIndex = idx;
        }

        function renderItems(items) {
            list.innerHTML = '';
            if (!items.length) {
                hideSuggest();
                return;
            }
            items.forEach(function (p, i) {
                var li = document.createElement('li');
                li.className = 'nav-search-suggest__item';
                li.setAttribute('role', 'option');
                li.dataset.index = String(i);
                var thumbUrl = (p.image_thumb || '').trim();
                var thumbHtml = thumbUrl
                    ? '<span class="nav-search-suggest__thumb"><img src="' +
                      esc(thumbUrl) +
                      '" alt="" width="40" height="40" loading="lazy" decoding="async"></span>'
                    : '<span class="nav-search-suggest__thumb nav-search-suggest__thumb--empty"><i class="fa-solid fa-box"></i></span>';
                li.innerHTML =
                    thumbHtml + '<span class="nav-search-suggest__nom">' + esc(p.nom || '') + '</span>';
                li.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    if (p.url) {
                        window.location.href = p.url;
                    }
                });
                list.appendChild(li);
            });
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            setActive(-1);
        }

        function fetchSuggest(q) {
            if (abort && typeof abort.abort === 'function') {
                abort.abort();
            }
            var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            abort = controller;
            var url =
                '/api/produit-search-suggest.php?q=' + encodeURIComponent(q) + '&limit=8';
            fetch(url, controller ? { signal: controller.signal } : {})
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    renderItems(data.items || []);
                })
                .catch(function () {
                    hideSuggest();
                })
                .finally(function () {
                    if (abort === controller) {
                        abort = null;
                    }
                });
        }

        function onInput() {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 1) {
                hideSuggest();
                return;
            }
            timer = setTimeout(function () {
                fetchSuggest(q);
            }, 220);
        }

        input.addEventListener('input', onInput);
        input.addEventListener('focus', function () {
            if (input.value.trim().length >= 1) {
                onInput();
            }
        });

        input.addEventListener('keydown', function (ev) {
            var items = list.querySelectorAll('.nav-search-suggest__item');
            if (!items.length || list.hidden) {
                return;
            }
            if (ev.key === 'ArrowDown') {
                ev.preventDefault();
                var next = activeIndex + 1;
                if (next >= items.length) {
                    next = 0;
                }
                setActive(next);
                items[next].scrollIntoView({ block: 'nearest' });
            } else if (ev.key === 'ArrowUp') {
                ev.preventDefault();
                var prev = activeIndex - 1;
                if (prev < 0) {
                    prev = items.length - 1;
                }
                setActive(prev);
                items[prev].scrollIntoView({ block: 'nearest' });
            } else if (ev.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                ev.preventDefault();
                items[activeIndex].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
            } else if (ev.key === 'Escape') {
                hideSuggest();
            }
        });

        document.addEventListener('click', function (ev) {
            if (!wrapper.contains(ev.target)) {
                hideSuggest();
            }
        });

        form.addEventListener('submit', function () {
            hideSuggest();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
