/**
 * Accueil — carrousels, vidéos slider, spotlight
 * Chargé en defer après jQuery + Owl.
 */
(function ($) {
    'use strict';

    if (!$) {
        return;
    }

    $(function () {
        var owlDefaults = {
            loop: true,
            dots: true,
            nav: true,
            navText: [
                '<i class="fa-solid fa-chevron-left"></i>',
                '<i class="fa-solid fa-chevron-right"></i>'
            ],
            smartSpeed: 450,
            autoplayHoverPause: true
        };

        function ensureSliderVideoLoaded(videoEl) {
            if (!videoEl) {
                return;
            }
            var source = videoEl.querySelector('source[data-src]');
            if (!source) {
                return;
            }
            var dataSrc = source.getAttribute('data-src');
            if (!dataSrc) {
                return;
            }
            source.setAttribute('src', dataSrc);
            source.removeAttribute('data-src');
            videoEl.load();
        }

        function playSliderVideo(videoEl) {
            if (!videoEl) {
                return;
            }
            ensureSliderVideoLoaded(videoEl);
            var tryPlay = function () {
                var playPromise = videoEl.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function () { /* autoplay bloqué */ });
                }
            };
            if (videoEl.readyState >= 2) {
                tryPlay();
            } else {
                videoEl.addEventListener('loadeddata', tryPlay, { once: true });
                videoEl.addEventListener('canplay', tryPlay, { once: true });
            }
        }

        function syncSliderVideos() {
            var $slider = $('.slider-area');
            var modalOpen = $('#home-video-modal').hasClass('is-open');
            $slider.find('.slider-item__video').each(function () {
                this.pause();
            });
            if (modalOpen) {
                return;
            }
            $slider.find('.owl-item.active .slider-item__video').each(function () {
                playSliderVideo(this);
            });
        }

        function updateVideoModalPlayButton(isPlaying) {
            var $btn = $('.home-video-modal__play');
            var $icon = $btn.find('i');
            if (isPlaying) {
                $icon.removeClass('fa-play').addClass('fa-pause');
                $btn.attr('aria-label', 'Pause');
            } else {
                $icon.removeClass('fa-pause').addClass('fa-play');
                $btn.attr('aria-label', 'Lecture');
            }
        }

        function closeHomeVideoModal() {
            var $modal = $('#home-video-modal');
            var modalVideo = $modal.find('.home-video-modal__video').get(0);
            if (modalVideo) {
                modalVideo.pause();
                modalVideo.removeAttribute('src');
                while (modalVideo.firstChild) {
                    modalVideo.removeChild(modalVideo.firstChild);
                }
                modalVideo.load();
            }
            $modal.removeClass('is-open').attr('hidden', true);
            $('body').removeClass('home-video-modal-open');
            syncSliderVideos();
        }

        function openHomeVideoModal(sourceVideo) {
            if (!sourceVideo) {
                return;
            }
            var $modal = $('#home-video-modal');
            var modalVideo = $modal.find('.home-video-modal__video').get(0);
            var $source = $(sourceVideo).find('source').first();
            var src = $source.attr('src') || $source.attr('data-src') || sourceVideo.currentSrc || sourceVideo.src;
            var type = $source.attr('type') || '';
            var title = $(sourceVideo).attr('aria-label') || 'Vidéo Sugar Paper';

            if (!src || !modalVideo) {
                return;
            }

            $('#home-video-modal-title').text(title);
            while (modalVideo.firstChild) {
                modalVideo.removeChild(modalVideo.firstChild);
            }
            if (type) {
                var sourceEl = document.createElement('source');
                sourceEl.src = src;
                sourceEl.type = type;
                modalVideo.appendChild(sourceEl);
            } else {
                modalVideo.src = src;
            }
            modalVideo.muted = true;
            modalVideo.loop = true;
            modalVideo.controls = false;
            modalVideo.currentTime = 0;
            modalVideo.load();

            $modal.addClass('is-open').removeAttr('hidden');
            $('body').addClass('home-video-modal-open');
            $('.slider-area .slider-item__video').each(function () {
                this.pause();
            });

            var playPromise = modalVideo.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () {
                    updateVideoModalPlayButton(false);
                });
            }
            updateVideoModalPlayButton(true);
        }

        $('.slider-area').on('click', '.slider-item__video', function (event) {
            event.preventDefault();
            event.stopPropagation();
            openHomeVideoModal(this);
        });

        $('.slider-area').on('keydown', '.slider-item__video', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openHomeVideoModal(this);
            }
        });

        $(document).on('click', '[data-video-modal-close]', function () {
            closeHomeVideoModal();
        });

        $('.home-video-modal__play').on('click', function () {
            var modalVideo = $('.home-video-modal__video').get(0);
            if (!modalVideo) {
                return;
            }
            if (modalVideo.paused) {
                var playPromise = modalVideo.play();
                if (playPromise && typeof playPromise.catch === 'function') {
                    playPromise.catch(function () { /* lecture bloquée */ });
                }
                updateVideoModalPlayButton(true);
            } else {
                modalVideo.pause();
                updateVideoModalPlayButton(false);
            }
        });

        $(document).on('keydown', function (event) {
            if (event.key === 'Escape' && $('#home-video-modal').hasClass('is-open')) {
                closeHomeVideoModal();
            }
        });

        function scaleServicesBannerInner() {
            var wrap = document.querySelector('.services-banner-inner-scale-wrap');
            var inner = document.querySelector('.services-banner-inner');
            if (!wrap || !inner) {
                return;
            }
            if (window.innerWidth < 993) {
                inner.style.transform = 'none';
                inner.style.width = '100%';
                wrap.style.height = 'auto';
                return;
            }
            inner.style.transform = 'none';
            inner.style.width = '100%';
            wrap.style.height = 'auto';
        }

        function scaleHomeSpotlight() {
            var wrap = document.querySelector('.services-banner-scale-wrap');
            var scaler = document.querySelector('.services-banner-scaler');
            if (!wrap || !scaler) {
                return;
            }
            if (window.innerWidth < 993) {
                scaler.style.width = '100%';
                scaler.style.transform = 'none';
                wrap.style.height = 'auto';
                return;
            }
            var designWidth = 1200;
            var available = wrap.clientWidth;
            if (available >= designWidth) {
                scaler.style.width = '100%';
                scaler.style.transform = 'none';
                wrap.style.height = 'auto';
                return;
            }
            var scale = available / designWidth;
            if (!isFinite(scale) || scale <= 0) {
                scale = 1;
            }
            if (scale > 1) {
                scale = 1;
            }
            scaler.style.width = designWidth + 'px';
            scaler.style.transform = 'scale(' + scale + ')';
            wrap.style.height = (scaler.offsetHeight * scale) + 'px';
        }

        function initHomeSpotlightSlider() {
            var $spotlight = $('.home-spotlight--slider');
            if (!$spotlight.length) {
                return;
            }

            $spotlight.each(function () {
                var $root = $(this).find('.home-spotlight__inner');
                var $slides = $root.find('.home-spotlight__slide');
                var $dots = $root.find('.home-spotlight__dot');
                var current = 0;
                var timer = null;

                function showSlide(index) {
                    if (!$slides.length) {
                        return;
                    }
                    current = (index + $slides.length) % $slides.length;
                    $slides.removeClass('is-active');
                    $slides.eq(current).addClass('is-active');
                    $dots.removeClass('home-spotlight__dot--active').attr('aria-selected', 'false');
                    $dots.eq(current).addClass('home-spotlight__dot--active').attr('aria-selected', 'true');
                    scaleHomeSpotlight();
                }

                function startAutoPlay() {
                    if (timer) {
                        clearInterval(timer);
                    }
                    if ($slides.length < 2) {
                        return;
                    }
                    timer = setInterval(function () {
                        showSlide(current + 1);
                    }, 5000);
                }

                $dots.on('click', function () {
                    var index = parseInt($(this).attr('data-slide-index'), 10);
                    if (isNaN(index)) {
                        return;
                    }
                    showSlide(index);
                    startAutoPlay();
                });

                showSlide(0);
                startAutoPlay();
            });
        }

        scaleServicesBannerInner();
        scaleHomeSpotlight();
        initHomeSpotlightSlider();
        $(window).on('resize', function () {
            scaleServicesBannerInner();
            scaleHomeSpotlight();
        });
        $('.home-spotlight__img').on('load', scaleHomeSpotlight);
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                scaleServicesBannerInner();
                scaleHomeSpotlight();
            });
        }

        var $homeSlider = $('.slider-area');
        if ($homeSlider.find('.slider-item').length) {
            var sliderIsVideoMode = $homeSlider.hasClass('slider-area--video-mode');
            var videoSlideCount = $homeSlider.find('.slider-item--video').length;

            if (sliderIsVideoMode) {
                var videoVisibleCount = Math.min(3, videoSlideCount);
                var videoTripleOptions = {
                    items: videoVisibleCount,
                    margin: 0,
                    stagePadding: 0,
                    autoplay: videoSlideCount > videoVisibleCount,
                    autoplayTimeout: 12000,
                    loop: videoSlideCount > videoVisibleCount,
                    lazyLoad: false,
                    nav: videoSlideCount > videoVisibleCount,
                    dots: videoSlideCount > videoVisibleCount,
                    responsive: {
                        0: {
                            items: videoVisibleCount,
                            margin: 0,
                            stagePadding: 0
                        },
                        480: {
                            items: videoVisibleCount,
                            margin: 0,
                            stagePadding: 0
                        },
                        768: {
                            items: videoVisibleCount,
                            margin: 0,
                            stagePadding: 0
                        },
                        992: {
                            items: videoVisibleCount,
                            margin: 0,
                            stagePadding: 0
                        }
                    }
                };
                $homeSlider.on('initialized.owl.carousel changed.owl.carousel translated.owl.carousel', function () {
                    syncSliderVideos();
                });
                $homeSlider.owlCarousel($.extend({}, owlDefaults, videoTripleOptions));
                syncSliderVideos();
            } else {
                $homeSlider.owlCarousel($.extend({}, owlDefaults, {
                    items: 1,
                    autoplay: true,
                    autoplayTimeout: 6000,
                    lazyLoad: true
                }));
            }
        }

        $('.categorie').owlCarousel($.extend({}, owlDefaults, {
            items: 5,
            autoplay: true,
            autoplayTimeout: 4500,
            stagePadding: 20,
            margin: 15,
            responsive: {
                0: { items: 1, stagePadding: 10, margin: 10 },
                350: { items: 2, stagePadding: 10, margin: 12 },
                576: { items: 2, stagePadding: 15, margin: 15 },
                768: { items: 3, stagePadding: 15, margin: 15 },
                992: { items: 4, stagePadding: 20, margin: 15 },
                1200: { items: 4, stagePadding: 20, margin: 15 }
            }
        }));
    });
})(window.jQuery);
