/**
 * Notation livreur — page suivi public (suivi-livraison.php).
 */
(function () {
    'use strict';

    var cfg = window.LIVREUR_TRACKING_CONFIG || {};
    var section = document.getElementById('livreur-rating-section');
    if (!section) {
        return;
    }

    var starsWrap = document.getElementById('livreur-rating-stars');
    var hintEl = document.getElementById('livreur-rating-hint');
    var thanksEl = document.getElementById('livreur-rating-thanks');
    var frozenEl = document.getElementById('livreur-rating-frozen');
    var frozenValueEl = document.getElementById('livreur-rating-frozen-value');
    var frozenStarsEl = document.getElementById('livreur-rating-frozen-stars');

    var state = cfg.ratingState || section.getAttribute('data-state') || 'pending';
    var currentNote = parseInt(cfg.existingRating, 10) || 0;
    var submitting = false;
    var pollTimer = null;

    function buildStarsHtml(note) {
        var html = '';
        var n = Math.max(0, Math.min(5, parseInt(note, 10) || 0));
        for (var i = 1; i <= 5; i++) {
            html += i <= n
                ? '<i class="fas fa-star livreur-note-star livreur-note-star--on" aria-hidden="true"></i>'
                : '<i class="far fa-star livreur-note-star livreur-note-star--off" aria-hidden="true"></i>';
        }
        return html;
    }

    function setInteractiveStars(note) {
        if (!starsWrap) return;
        var buttons = starsWrap.querySelectorAll('.livreur-rating__star');
        buttons.forEach(function (btn) {
            var val = parseInt(btn.getAttribute('data-star'), 10);
            var icon = btn.querySelector('i');
            if (!icon) return;
            icon.className = val <= note ? 'fas fa-star livreur-rating__star--filled' : 'far fa-star';
        });
    }

    function setState(newState) {
        state = newState;
        section.setAttribute('data-state', newState);
    }

    function enableRatingUi() {
        if (state === 'rated') return;
        setState('ready');
        if (hintEl) {
            hintEl.textContent = 'Touchez une étoile pour noter la livraison.';
        }
        if (starsWrap) {
            starsWrap.querySelectorAll('.livreur-rating__star').forEach(function (btn) {
                btn.disabled = false;
            });
        }
    }

    function showRated(note) {
        currentNote = note;
        setState('rated');
        if (starsWrap) {
            starsWrap.hidden = true;
        }
        if (hintEl) {
            hintEl.hidden = true;
        }
        if (thanksEl) {
            thanksEl.hidden = false;
            thanksEl.classList.add('is-visible');
        }
        if (frozenEl) {
            frozenEl.hidden = false;
        }
        if (frozenStarsEl) {
            frozenStarsEl.innerHTML = buildStarsHtml(note);
        }
        if (frozenValueEl) {
            frozenValueEl.textContent = String(note);
        }
        window.setTimeout(function () {
            if (thanksEl) {
                thanksEl.classList.add('is-settled');
            }
        }, 2200);
    }

    function submitRating(note) {
        if (submitting || state === 'rated' || note < 1 || note > 5) {
            return;
        }
        submitting = true;
        if (hintEl) {
            hintEl.textContent = 'Enregistrement…';
        }
        starsWrap.querySelectorAll('.livreur-rating__star').forEach(function (btn) {
            btn.disabled = true;
        });

        var payload = {
            token: cfg.ratingToken || cfg.publicWatchToken || cfg.embeddedWatchToken || '',
            note: note
        };
        if (cfg.livraisonType === 'facture' || cfg.blId) {
            payload.bl_id = cfg.blId || 0;
        } else {
            payload.commande_id = cfg.commandeId || 0;
        }

        fetch(cfg.ratingApiUrl || '/api/tracking/rate-livreur.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                submitting = false;
                if (result.data && result.data.success) {
                    showRated(parseInt(result.data.note, 10) || note);
                    return;
                }
                if (result.data && result.data.note) {
                    showRated(parseInt(result.data.note, 10));
                    return;
                }
                if (hintEl) {
                    hintEl.textContent = (result.data && result.data.message) || 'Impossible d\'enregistrer la note.';
                }
                if (state === 'ready') {
                    starsWrap.querySelectorAll('.livreur-rating__star').forEach(function (btn) {
                        btn.disabled = false;
                    });
                }
            })
            .catch(function () {
                submitting = false;
                if (hintEl) {
                    hintEl.textContent = 'Erreur réseau. Réessayez.';
                }
                if (state === 'ready') {
                    starsWrap.querySelectorAll('.livreur-rating__star').forEach(function (btn) {
                        btn.disabled = false;
                    });
                }
            });
    }

    function bindStars() {
        if (!starsWrap) return;
        starsWrap.querySelectorAll('.livreur-rating__star').forEach(function (btn) {
            btn.addEventListener('mouseenter', function () {
                if (state !== 'ready' || submitting) return;
                setInteractiveStars(parseInt(btn.getAttribute('data-star'), 10));
            });
            btn.addEventListener('focus', function () {
                if (state !== 'ready' || submitting) return;
                setInteractiveStars(parseInt(btn.getAttribute('data-star'), 10));
            });
            btn.addEventListener('click', function () {
                if (state !== 'ready' || submitting) return;
                submitRating(parseInt(btn.getAttribute('data-star'), 10));
            });
        });
        starsWrap.addEventListener('mouseleave', function () {
            if (state !== 'ready') return;
            setInteractiveStars(currentNote);
        });
    }

    function pollArrivee() {
        if (state === 'rated' || state === 'ready') {
            return;
        }
        var url = cfg.lastPositionUrl || '/api/tracking/last-position.php';
        var params = new URLSearchParams();
        var token = cfg.ratingToken || cfg.publicWatchToken || cfg.embeddedWatchToken || '';
        if (token) params.set('token', token);
        if (cfg.livraisonType === 'facture' || cfg.blId) {
            params.set('bl_id', String(cfg.blId || 0));
        } else {
            params.set('commande_id', String(cfg.commandeId || 0));
        }
        fetch(url + '?' + params.toString(), { cache: 'no-store' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.can_rate_livreur) {
                    enableRatingUi();
                    if (pollTimer) {
                        window.clearInterval(pollTimer);
                        pollTimer = null;
                    }
                }
            })
            .catch(function () { /* ignore */ });
    }

    if (state === 'rated') {
        if (starsWrap) starsWrap.hidden = true;
        if (hintEl) hintEl.hidden = true;
        if (frozenEl) frozenEl.hidden = false;
    } else if (state === 'ready') {
        bindStars();
    } else {
        bindStars();
        pollArrivee();
        pollTimer = window.setInterval(pollArrivee, 12000);
    }
})();
