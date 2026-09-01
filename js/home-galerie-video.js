/**
 * Galerie vidéos accueil — aperçu 1ère image + lecture lazy.
 */
(function () {
  'use strict';

  function loadVideoSource(wrapper, video) {
    if (video.dataset.loaded === '1') {
      return;
    }
    var src = video.getAttribute('data-src');
    var source = video.querySelector('source');
    if (src && source && !source.getAttribute('src')) {
      source.setAttribute('src', src);
      video.load();
    }
    video.dataset.loaded = '1';
    wrapper.setAttribute('data-video-loaded', '1');
  }

  function ensurePosterImg(wrapper) {
    var img = wrapper.querySelector('img.galerie-poster');
    if (img) {
      return img;
    }
    var placeholder = wrapper.querySelector('.galerie-poster--placeholder');
    img = document.createElement('img');
    img.className = 'galerie-poster';
    img.alt = wrapper.getAttribute('data-video-title') || 'Aperçu vidéo';
    img.loading = 'lazy';
    img.decoding = 'async';
    if (placeholder) {
      placeholder.replaceWith(img);
    } else {
      wrapper.insertBefore(img, wrapper.firstChild);
    }
    return img;
  }

  function hidePlaceholder(wrapper) {
    var placeholder = wrapper.querySelector('.galerie-poster--placeholder');
    if (placeholder) {
      placeholder.style.display = 'none';
    }
  }

  function initVideoFramePreview(wrapper, video) {
    if (wrapper.getAttribute('data-needs-poster') !== '1') {
      return;
    }
    if (wrapper.getAttribute('data-poster-done') === '1' || wrapper.getAttribute('data-poster-pending') === '1') {
      return;
    }

    var src = video.getAttribute('data-src');
    if (!src) {
      return;
    }

    wrapper.setAttribute('data-poster-pending', '1');

    video.muted = true;
    video.playsInline = true;
    video.preload = 'metadata';
    video.classList.add('galerie-video--preview');

    function markDone() {
      hidePlaceholder(wrapper);
      wrapper.setAttribute('data-poster-done', '1');
      wrapper.removeAttribute('data-poster-pending');
    }

    function seekToPreviewFrame() {
      if (!video.duration || !isFinite(video.duration)) {
        return;
      }
      var seekTo = Math.min(1.2, Math.max(0.12, video.duration * 0.08));
      try {
        video.currentTime = seekTo;
      } catch (e) {
        markDone();
      }
    }

    video.addEventListener('loadedmetadata', seekToPreviewFrame);
    video.addEventListener('seeked', function () {
      video.pause();
      markDone();
    });
    video.addEventListener('error', markDone);

    if (!video.getAttribute('src')) {
      video.setAttribute('src', src);
    }
    var source = video.querySelector('source');
    if (source && !source.getAttribute('src')) {
      source.setAttribute('src', src);
    }
    video.load();
  }

  function capturePosterFromVideo(wrapper) {
    if (wrapper.getAttribute('data-poster-pending') === '1' || wrapper.getAttribute('data-poster-done') === '1') {
      return;
    }

    var videoEl = wrapper.querySelector('.galerie-video');
    if (!videoEl) {
      return;
    }

    var src = videoEl.getAttribute('data-src');
    if (!src) {
      return;
    }

    wrapper.setAttribute('data-poster-pending', '1');

    var probe = document.createElement('video');
    probe.muted = true;
    probe.playsInline = true;
    probe.preload = 'auto';
    probe.setAttribute('playsinline', '');
    probe.src = src;

    var cleaned = false;
    function cleanup() {
      if (cleaned) {
        return;
      }
      cleaned = true;
      probe.removeAttribute('src');
      probe.load();
      probe.remove();
      wrapper.removeAttribute('data-poster-pending');
    }

    probe.addEventListener('error', function () {
      cleanup();
      initVideoFramePreview(wrapper, videoEl);
    });

    probe.addEventListener('loadeddata', function () {
      var seekTo = Math.min(1.2, Math.max(0.12, (probe.duration || 2) * 0.08));
      try {
        probe.currentTime = seekTo;
      } catch (e) {
        cleanup();
        initVideoFramePreview(wrapper, videoEl);
      }
    });

    probe.addEventListener('seeked', function () {
      if (!probe.videoWidth || !probe.videoHeight) {
        cleanup();
        initVideoFramePreview(wrapper, videoEl);
        return;
      }
      try {
        var canvas = document.createElement('canvas');
        canvas.width = probe.videoWidth;
        canvas.height = probe.videoHeight;
        var ctx = canvas.getContext('2d');
        if (!ctx) {
          cleanup();
          initVideoFramePreview(wrapper, videoEl);
          return;
        }
        ctx.drawImage(probe, 0, 0, canvas.width, canvas.height);
        var dataUrl = canvas.toDataURL('image/jpeg', 0.82);
        var img = ensurePosterImg(wrapper);
        img.src = dataUrl;
        videoEl.setAttribute('poster', dataUrl);
        videoEl.classList.remove('galerie-video--preview');
        hidePlaceholder(wrapper);
        wrapper.setAttribute('data-poster-done', '1');
      } catch (err) {
        initVideoFramePreview(wrapper, videoEl);
      }
      cleanup();
    });
  }

  function schedulePosterCapture(wrapper) {
    if (wrapper.getAttribute('data-needs-poster') !== '1') {
      return;
    }

    function runCapture() {
      initVideoFramePreview(wrapper, wrapper.querySelector('.galerie-video'));
    }

    if ('IntersectionObserver' in window) {
      var obs = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            runCapture();
            obs.unobserve(wrapper);
          }
        });
      }, { rootMargin: '120px', threshold: 0.05 });
      obs.observe(wrapper);
    } else {
      runCapture();
    }
  }

  function initWrapper(wrapper) {
    var video = wrapper.querySelector('.galerie-video');
    var playBtn = wrapper.querySelector('.galerie-play-overlay');
    if (!video || !playBtn) {
      return;
    }

    function getPosterEl() {
      return wrapper.querySelector('img.galerie-poster');
    }

    schedulePosterCapture(wrapper);

    function hidePoster() {
      var poster = getPosterEl() || wrapper.querySelector('.galerie-poster--placeholder');
      if (poster) {
        poster.style.display = 'none';
      }
      video.classList.remove('galerie-video--preview');
      video.classList.add('is-active');
      playBtn.style.opacity = '0';
      playBtn.style.pointerEvents = 'none';
    }

    function showPoster() {
      var poster = getPosterEl() || wrapper.querySelector('.galerie-poster--placeholder');
      if (poster) {
        poster.style.display = '';
      }
      if (wrapper.getAttribute('data-needs-poster') === '1' && wrapper.getAttribute('data-poster-done') === '1') {
        video.classList.add('galerie-video--preview');
      }
      video.classList.remove('is-active');
      playBtn.style.opacity = '';
      playBtn.style.pointerEvents = '';
    }

    playBtn.addEventListener('click', function () {
      loadVideoSource(wrapper, video);
      video.classList.remove('galerie-video--preview');
      hidePoster();
      video.play().catch(function () {});
    });

    video.addEventListener('play', hidePoster);
    video.addEventListener('pause', function () {
      if (video.currentTime === 0 || video.ended) {
        showPoster();
      }
    });
    video.addEventListener('ended', showPoster);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.galerie-video-wrapper').forEach(initWrapper);
  });
})();
