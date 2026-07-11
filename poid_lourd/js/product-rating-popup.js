(function () {
    'use strict';

    function roundThird(val) {
        var steps = 15;
        var best = 1 / 3;
        var bestDiff = Math.abs(val - best);
        for (var i = 1; i <= steps; i++) {
            var s = Math.round((i / 3) * 100) / 100;
            var d = Math.abs(val - s);
            if (d < bestDiff) {
                bestDiff = d;
                best = s;
            }
        }
        return Math.max(0.33, Math.min(5, best));
    }

    function noteToPct(note) {
        return Math.max(0, Math.min(100, (note / 5) * 100));
    }

    function showToast(message) {
        if (!message) {
            return;
        }
        var toast = document.createElement('div');
        toast.className = 'pr-toast-ok';
        toast.innerHTML = '<i class="fa-solid fa-gift" aria-hidden="true"></i> ' + message;
        document.body.appendChild(toast);
        requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });
        setTimeout(function () {
            toast.classList.remove('is-visible');
            setTimeout(function () { toast.remove(); }, 350);
        }, 3200);
    }

    function playBigStarBurst(item) {
        if (!item) {
            return;
        }
        var existing = item.querySelector('.pr-popup-item__star-burst');
        if (existing) {
            existing.remove();
        }
        var burst = document.createElement('div');
        burst.className = 'pr-popup-item__star-burst';
        burst.setAttribute('aria-hidden', 'true');
        burst.innerHTML = '<i class="fa-solid fa-star"></i>';
        item.appendChild(burst);
        setTimeout(function () {
            if (burst.parentNode) {
                burst.remove();
            }
        }, 950);
    }

    function hideNoterButton(commandeId) {
        if (!commandeId) {
            return;
        }
        document.querySelectorAll('.uc-btn-open-rating[data-commande-id="' + commandeId + '"]').forEach(function (btn) {
            btn.remove();
        });
    }

    function getUnsavedItems(popup) {
        return Array.from(popup.querySelectorAll('.pr-popup-item')).filter(function (item) {
            return item.dataset.prSaved !== '1';
        });
    }

    function getVisibleUnsavedItems(popup) {
        return getUnsavedItems(popup).filter(function (item) {
            return item.style.display !== 'none' && !item.hidden;
        });
    }

    function hasUnsavedForCommande(popup, commandeId) {
        return getUnsavedItems(popup).some(function (item) {
            return String(item.dataset.commandeId) === String(commandeId);
        });
    }

    function filterPopupItems(popup, commandeId) {
        var visible = 0;
        popup.querySelectorAll('.pr-popup-item').forEach(function (item) {
            var match = !commandeId || String(item.dataset.commandeId) === String(commandeId);
            var show = match && item.dataset.prSaved !== '1';
            item.hidden = !show;
            item.style.display = show ? '' : 'none';
            if (show) {
                visible++;
            }
        });
        return visible;
    }

    function resetPopupItemsFilter(popup) {
        popup.querySelectorAll('.pr-popup-item').forEach(function (item) {
            if (item.dataset.prSaved === '1') {
                item.hidden = true;
                item.style.display = 'none';
            } else {
                item.hidden = false;
                item.style.display = '';
            }
        });
        popup.dataset.prFilterCommande = '';
    }

    function saveRating(item, note, popup, onDone) {
        if (!item || item.dataset.prSaved === '1' || item.dataset.prSaving === '1') {
            return;
        }
        var pid = parseInt(item.dataset.produitId || '0', 10);
        var cid = parseInt(item.dataset.commandeId || '0', 10);
        if (pid <= 0 || cid <= 0 || note < 0.33) {
            return;
        }

        item.dataset.prSaving = '1';
        var fd = new FormData();
        fd.append('action', 'save_one');
        fd.append('produit_id', String(pid));
        fd.append('commande_id', String(cid));
        fd.append('note', String(note));

        fetch('/user/avis-produits-action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                item.dataset.prSaving = '0';
                if (!data || !data.success) {
                    alert((data && data.message) ? data.message : 'Enregistrement impossible.');
                    return;
                }
                item.dataset.prSaved = '1';
                item.classList.add('is-rated', 'is-saved');
                playBigStarBurst(item);

                var hint = item.querySelector('.pr-popup-item__hint');
                if (hint) {
                    hint.innerHTML = '<i class="fa-solid fa-circle-check"></i> Note enregistrée — <strong>+' + Math.round(note * 10) + ' pts bonus</strong>';
                }

                setTimeout(function () {
                    item.classList.add('is-removing');
                }, 700);

                if (!hasUnsavedForCommande(popup, cid)) {
                    hideNoterButton(cid);
                }

                if (typeof onDone === 'function') {
                    onDone(data, cid);
                }
            })
            .catch(function () {
                item.dataset.prSaving = '0';
                alert('Erreur réseau.');
            });
    }

    function checkPopupComplete(popup) {
        var filterId = popup.dataset.prFilterCommande || '';
        var pending = filterId
            ? getVisibleUnsavedItems(popup)
            : getUnsavedItems(popup);

        if (pending.length > 0) {
            return;
        }

        setTimeout(function () {
            closePopup(popup);
            showToast('Merci ! Vos points bonus ont été crédités.');
        }, 900);
    }

    function bindInteractiveStars(container, popup) {
        if (!container || container.dataset.prBound === '1') {
            return;
        }
        container.dataset.prBound = '1';
        var track = container.querySelector('.pr-stars__track');
        var item = container.closest('.pr-popup-item');
        var hint = item && item.querySelector('.pr-popup-item__hint');
        var input = item && item.querySelector('.pr-popup-note-input');
        var current = input ? parseFloat(input.value || '0') : 0;

        function setNote(note, animate, preview) {
            current = roundThird(note);
            container.style.setProperty('--pr-rating', noteToPct(current));
            if (input) {
                input.value = String(current);
            }
            if (hint && preview) {
                hint.textContent = 'Relâchez pour valider…';
            } else if (hint && !item.classList.contains('is-saved')) {
                hint.textContent = current > 0
                    ? 'Votre note : ' + current.toFixed(2).replace('.', ',') + ' / 5'
                    : 'Touchez les étoiles pour noter';
            }
            if (animate) {
                container.classList.remove('is-burst');
                void container.offsetWidth;
                container.classList.add('is-burst');
                spawnSparks(container);
            }
        }

        function posToNote(clientX) {
            var rect = track.getBoundingClientRect();
            if (rect.width <= 0) {
                return 0;
            }
            var ratio = (clientX - rect.left) / rect.width;
            ratio = Math.max(0, Math.min(1, ratio));
            return roundThird(ratio * 5);
        }

        function onMove(e) {
            if (item && item.dataset.prSaved === '1') {
                return;
            }
            var x = e.touches ? e.touches[0].clientX : e.clientX;
            setNote(posToNote(x), false, true);
        }

        function onEnd(e) {
            if (item && item.dataset.prSaved === '1') {
                return;
            }
            var x = (e.changedTouches ? e.changedTouches[0].clientX : e.clientX);
            var finalNote = posToNote(x);
            if (finalNote < 0.33) {
                return;
            }
            setNote(finalNote, true, false);
            saveRating(item, finalNote, popup, function () {
                setTimeout(function () {
                    checkPopupComplete(popup);
                }, 850);
            });
        }

        track.addEventListener('mousemove', onMove);
        track.addEventListener('touchmove', onMove, { passive: true });
        track.addEventListener('click', onEnd);
        track.addEventListener('touchend', onEnd);

        if (current > 0) {
            setNote(current, false, false);
        }
    }

    function spawnSparks(el) {
        var rect = el.getBoundingClientRect();
        for (var i = 0; i < 6; i++) {
            var sp = document.createElement('span');
            sp.className = 'pr-popup__spark';
            sp.style.left = (rect.width * 0.2 + Math.random() * rect.width * 0.6) + 'px';
            sp.style.top = '50%';
            sp.style.setProperty('--sx', ((Math.random() - 0.5) * 60) + 'px');
            sp.style.setProperty('--sy', (-30 - Math.random() * 40) + 'px');
            el.appendChild(sp);
            setTimeout(function (node) {
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            }, 700, sp);
        }
    }

    function openPopup(root) {
        if (!root) {
            return;
        }
        root.classList.add('is-open');
        root.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
        root.querySelectorAll('.pr-stars--input').forEach(function (el) {
            bindInteractiveStars(el, root);
        });
    }

    function openPopupForCommande(root, commandeId) {
        if (!root) {
            return;
        }
        var visible = filterPopupItems(root, commandeId);
        if (visible <= 0) {
            showToast('Tous les produits de cette commande ont déjà été notés.');
            return;
        }
        root.dataset.prFilterCommande = commandeId ? String(commandeId) : '';
        openPopup(root);
    }

    function closePopup(root) {
        if (!root) {
            return;
        }
        root.classList.remove('is-open');
        root.setAttribute('aria-hidden', 'true');
        document.documentElement.style.overflow = '';
        resetPopupItemsFilter(root);
    }

    function snoozeAndClose(root) {
        var fd = new FormData();
        fd.append('action', 'snooze');
        fetch('/user/avis-produits-action.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .finally(function () { closePopup(root); });
    }

    function initPopup() {
        var popup = document.getElementById('prRatingPopup');
        if (!popup) {
            return;
        }

        var btnLater = document.getElementById('prRatingLater');
        var backdrop = popup.querySelector('.pr-popup__backdrop');
        var autoCmd = parseInt(popup.dataset.prAutoCommande || '0', 10);

        if (popup.dataset.prAutoOpen === '1') {
            setTimeout(function () {
                if (autoCmd > 0) {
                    openPopupForCommande(popup, autoCmd);
                } else {
                    resetPopupItemsFilter(popup);
                    openPopup(popup);
                }
            }, 400);
        }

        document.querySelectorAll('.uc-btn-open-rating').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cid = btn.getAttribute('data-commande-id');
                openPopupForCommande(popup, cid);
            });
        });

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                snoozeAndClose(popup);
            });
        }

        if (btnLater) {
            btnLater.addEventListener('click', function () {
                snoozeAndClose(popup);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPopup);
    } else {
        initPopup();
    }
})();
