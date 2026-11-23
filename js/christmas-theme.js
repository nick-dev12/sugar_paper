(() => {
    const script = document.currentScript;
    const root = document.documentElement;
    const previewKey = 'sugar-paper:noel-preview';
    const pauseKey = 'sugar-paper:noel-paused';
    const preview = new URLSearchParams(location.search).get('noel');

    function readPreference(key) {
        try {
            return sessionStorage.getItem(key);
        } catch {
            return null;
        }
    }

    function writePreference(key, value) {
        try {
            sessionStorage.setItem(key, value);
        } catch {
            // L'aperçu et la pause restent utilisables sur la page courante.
        }
    }

    if (preview === '1' || preview === '0') {
        writePreference(previewKey, preview);
    }
    const active = script.dataset.enabled === '1'
        || (preview === null ? readPreference(previewKey) === '1' : preview === '1');
    if (!active) return;

    const stylesheet = document.createElement('link');
    stylesheet.rel = 'stylesheet';
    const styleUrl = new URL('../css/christmas-theme.css', script.src);
    if (script.dataset.styleVersion) styleUrl.searchParams.set('v', script.dataset.styleVersion);
    stylesheet.href = styleUrl.href;
    stylesheet.addEventListener('load', startTheme, { once: true });
    document.head.append(stylesheet);

    function startTheme() {
        root.classList.add('sp-noel');
        const homeSlider = document.querySelector('.page-home > .slider-area');
        if (homeSlider) {
            const display = document.createElement('section');
            display.className = 'sp-noel-vitrine';
            const title = document.createElement('h2');
            title.textContent = 'Noël à l’atelier';
            display.append(title);
            homeSlider.before(display);
        }
        const frame = document.createElement('div');
        frame.className = 'sp-noel-frame';
        frame.setAttribute('aria-hidden', 'true');
        document.body.append(frame);

        const count = Number(script.dataset.snowflakes);
        if (script.dataset.snow !== '1' || count < 1) return;

        const snow = document.createElement('div');
        snow.className = 'sp-noel-snow';
        snow.setAttribute('aria-hidden', 'true');
        for (let index = 0; index < count; index++) {
            const flake = document.createElement('span');
            flake.className = index % 4 === 0 ? 'sp-noel-flake sp-noel-flake--star' : 'sp-noel-flake';
            const duration = 14 + (index * 7 % 13);
            const size = index % 4 === 0 ? 10 + (index % 8) : 3 + (index % 4);
            flake.style.setProperty('--x', `${4 + (index * 37 % 91)}%`);
            flake.style.setProperty('--drift', `${(index % 2 ? 1 : -1) * (12 + index * 17 % 43)}px`);
            flake.style.setProperty('--size', `${size}px`);
            flake.style.setProperty('--duration', `${duration}s`);
            flake.style.setProperty('--delay', `-${duration * ((index * 31 % 97 + 1) / 100)}s`);
            snow.append(flake);
        }
        document.body.append(snow);

        let paused = readPreference(pauseKey) === '1';
        const buttons = [];
        const pageButton = makePauseButton();
        pageButton.classList.add('sp-noel-pause--page');
        document.body.append(pageButton);
        const checkoutClose = document.querySelector('#ckm-close');
        if (checkoutClose) checkoutClose.before(makePauseButton());

        function makePauseButton() {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sp-noel-pause';
            button.setAttribute('aria-label', 'Chute de neige en pause');
            button.addEventListener('click', () => {
                paused = !paused;
                writePreference(pauseKey, paused ? '1' : '0');
                updatePause();
            });
            buttons.push(button);
            return button;
        }

        function updatePause() {
            root.classList.toggle('sp-noel-paused', paused);
            for (const button of buttons) {
                button.setAttribute('aria-pressed', String(paused));
                button.title = paused ? 'Relancer les flocons' : 'Mettre les flocons en pause';
            }
        }

        function updateVisibility() {
            root.classList.toggle('sp-noel-hidden', document.hidden);
        }

        updatePause();
        updateVisibility();
        document.addEventListener('visibilitychange', updateVisibility);
    }
})();
