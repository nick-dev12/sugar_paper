/**
 * Upload multiple images + prévisualisation (UI uniquement)
 */
document.addEventListener('DOMContentLoaded', function () {
  var fileInput = document.getElementById('images_reference');
  var uploadBox = document.getElementById('upload-reference-box');
  var uploadTrigger = document.getElementById('upload-reference-trigger');
  var previewGrid = document.getElementById('preview-reference-grid');
  var uploadCounter = document.getElementById('upload-counter');
  var maxFiles = 6;
  var selectedFiles = [];
  var allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

  if (fileInput && uploadBox && uploadTrigger && previewGrid) {
  function isAllowedFile(file) {
    return file && allowedTypes.indexOf(file.type) !== -1;
  }

  function updateCounter() {
    if (!uploadCounter) {
      return;
    }
    uploadCounter.textContent = selectedFiles.length + ' / ' + maxFiles + ' image(s) sélectionnée(s)';
  }

  function syncInputFiles() {
    var dataTransfer = new DataTransfer();
    selectedFiles.forEach(function (file) {
      dataTransfer.items.add(file);
    });
    fileInput.files = dataTransfer.files;
  }

  function renderPreviews() {
    previewGrid.innerHTML = '';

    if (!selectedFiles.length) {
      previewGrid.classList.remove('show');
      updateCounter();
      syncInputFiles();
      return;
    }

    previewGrid.classList.add('show');

    selectedFiles.forEach(function (file, index) {
      var item = document.createElement('div');
      item.className = 'preview-reference-item';

      var image = document.createElement('img');
      image.alt = 'Prévisualisation ' + (index + 1);
      image.src = URL.createObjectURL(file);

      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'preview-reference-remove';
      removeBtn.setAttribute('aria-label', 'Retirer cette image');
      removeBtn.innerHTML = '<i class="fas fa-times" aria-hidden="true"></i>';
      removeBtn.addEventListener('click', function () {
        URL.revokeObjectURL(image.src);
        selectedFiles.splice(index, 1);
        renderPreviews();
      });

      var name = document.createElement('span');
      name.className = 'preview-reference-name';
      name.textContent = file.name;

      item.appendChild(image);
      item.appendChild(removeBtn);
      item.appendChild(name);
      previewGrid.appendChild(item);
    });

    updateCounter();
    syncInputFiles();
  }

  function addFiles(fileList) {
    if (!fileList || !fileList.length) {
      return;
    }

    for (var i = 0; i < fileList.length; i++) {
      if (selectedFiles.length >= maxFiles) {
        break;
      }
      var file = fileList[i];
      if (!isAllowedFile(file)) {
        continue;
      }
      var alreadySelected = selectedFiles.some(function (existing) {
        return existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified;
      });
      if (!alreadySelected) {
        selectedFiles.push(file);
      }
    }

    renderPreviews();
  }

  uploadTrigger.addEventListener('click', function () {
    fileInput.click();
  });

  fileInput.addEventListener('change', function () {
    addFiles(fileInput.files);
  });

  uploadBox.addEventListener('dragover', function (event) {
    event.preventDefault();
    uploadBox.classList.add('is-dragover');
  });

  uploadBox.addEventListener('dragleave', function () {
    uploadBox.classList.remove('is-dragover');
  });

  uploadBox.addEventListener('drop', function (event) {
    event.preventDefault();
    uploadBox.classList.remove('is-dragover');
    addFiles(event.dataTransfer.files);
  });

  updateCounter();
  }

  if (typeof window.initAuthIntlTel === 'function') {
    window.cpTelIti = window.initAuthIntlTel('telephone', {
      separateDialCode: true,
      nationalMode: true,
      formatOnDisplay: false,
      autoPlaceholder: 'aggressive',
      dropdownContainer: document.body
    });
    var telInput = document.getElementById('telephone');
    if (window.cpTelIti && telInput && telInput.value.trim() !== '') {
      window.cpTelIti.setNumber(telInput.value.trim());
    }
  }

  initCpVoiceNote();
  initCpCatalogueShop();

  var formPerso = document.getElementById('form-commande-perso');
  var loaderOverlay = document.getElementById('commande-loader-overlay');
  var persoSubmitting = false;
  var MIN_LOADER_MS = 600;

  function applyCpPhoneE164() {
    var input = document.getElementById('telephone');
    if (!input || !window.cpTelIti) {
      return;
    }
    try {
      var n = '';
      if (typeof intlTelInput !== 'undefined' && intlTelInput.utils) {
        n = window.cpTelIti.getNumber(intlTelInput.utils.numberFormat.E164);
      } else {
        n = window.cpTelIti.getNumber();
      }
      if (n) {
        input.value = n;
      }
    } catch (e) {}
  }

  if (formPerso && loaderOverlay) {
    formPerso.addEventListener('submit', function (event) {
      if (persoSubmitting) {
        event.preventDefault();
        return;
      }
      applyCpPhoneE164();
      if (!formPerso.checkValidity()) {
        return;
      }
      event.preventDefault();
      persoSubmitting = true;

      loaderOverlay.hidden = false;
      loaderOverlay.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      var submitBtn = formPerso.querySelector('.btn-submit');
      if (submitBtn) {
        submitBtn.disabled = true;
      }

      setTimeout(function () {
        applyCpPhoneE164();
        formPerso.submit();
      }, MIN_LOADER_MS);
    });
  }
});

function initCpCatalogueShop() {
  var cards = Array.prototype.slice.call(document.querySelectorAll('.cp-product-card'));
  var sections = Array.prototype.slice.call(document.querySelectorAll('.cp-dossier-section'));

  var searchInput = document.getElementById('cp-search');
  var sortSelect = document.getElementById('cp-sort');
  var visibleCountEl = document.getElementById('cp-visible-count');
  var resultsText = document.getElementById('cp-results-text');
  var emptyEl = document.querySelector('.cp-shop-empty--filter');
  var modalOverlay = document.getElementById('cp-modal-overlay');
  var modalBackdrop = document.getElementById('cp-modal-backdrop');
  var formProductName = document.getElementById('cp-form-product-name');
  var formProductImage = document.getElementById('cp-form-product-image');
  var formProductRange = document.getElementById('cp-form-product-range');
  var prixInput = document.getElementById('prix_propose');
  var clearBtn = document.getElementById('cp-clear-selection');
  var hiddenId = document.getElementById('catalogue_produit_id');
  var hiddenType = document.getElementById('type_produit');
  var totalProducts = cards.length;

  function formatPrice(value) {
    var n = Math.round(parseFloat(value) || 0);
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  function lockBodyScroll(lock) {
    document.body.style.overflow = lock ? 'hidden' : '';
  }

  function applyFilters() {
    if (!cards.length) {
      return;
    }

    var query = searchInput ? searchInput.value.trim().toLowerCase() : '';
    var visible = 0;

    cards.forEach(function (card) {
      var name = (card.getAttribute('data-name') || '').toLowerCase();
      var show = !query || name.indexOf(query) !== -1;
      card.classList.toggle('is-hidden', !show);
      if (show) {
        visible += 1;
      }
    });

    sections.forEach(function (section) {
      var sectionVisible = section.querySelectorAll('.cp-product-card:not(.is-hidden)').length;
      section.hidden = sectionVisible === 0;
    });

    if (visibleCountEl) {
      visibleCountEl.textContent = String(visible);
    }
    if (resultsText && totalProducts > 0) {
      resultsText.innerHTML = 'Affichage de <strong id="cp-visible-count">' + visible + '</strong> sur ' + totalProducts + ' produit(s)';
      visibleCountEl = document.getElementById('cp-visible-count');
    }
    if (emptyEl) {
      emptyEl.hidden = visible > 0;
    }
  }

  function sortCards() {
    if (!sortSelect || sortSelect.value === 'default' || !cards.length) {
      return;
    }

    document.querySelectorAll('.cp-product-grid').forEach(function (grid) {
      var gridCards = Array.prototype.slice.call(grid.querySelectorAll('.cp-product-card'));
      gridCards.sort(function (a, b) {
        if (sortSelect.value === 'name-asc') {
          return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
        }
        var aMin = parseFloat(a.getAttribute('data-price-min') || '0');
        var bMin = parseFloat(b.getAttribute('data-price-min') || '0');
        if (sortSelect.value === 'price-asc') {
          return aMin - bMin;
        }
        if (sortSelect.value === 'price-desc') {
          return bMin - aMin;
        }
        return 0;
      });
      gridCards.forEach(function (card) {
        grid.appendChild(card);
      });
    });
  }

  function openModal() {
    if (!modalOverlay) {
      return;
    }
    modalOverlay.hidden = false;
    modalOverlay.classList.add('is-visible');
    modalOverlay.setAttribute('aria-hidden', 'false');
    lockBodyScroll(true);
  }

  function closeModal() {
    if (!modalOverlay) {
      return;
    }
    modalOverlay.hidden = true;
    modalOverlay.classList.remove('is-visible');
    modalOverlay.setAttribute('aria-hidden', 'true');
    lockBodyScroll(false);
  }

  function showForm(card) {
    cards.forEach(function (c) {
      c.classList.remove('is-selected');
    });
    card.classList.add('is-selected');

    var id = card.getAttribute('data-product-id') || '';
    var name = card.getAttribute('data-name') || '';
    var image = card.getAttribute('data-image') || '';
    var priceMin = parseFloat(card.getAttribute('data-price-min') || '0');
    var priceMax = parseFloat(card.getAttribute('data-price-max') || '0');

    if (hiddenId) {
      hiddenId.value = id;
    }
    if (hiddenType) {
      hiddenType.value = name;
    }
    if (formProductName) {
      formProductName.textContent = name;
    }
    if (formProductImage) {
      if (image) {
        formProductImage.src = image;
        formProductImage.hidden = false;
      } else {
        formProductImage.hidden = true;
      }
    }
    if (formProductRange) {
      formProductRange.textContent = 'Fourchette : ' + formatPrice(priceMin) + ' — ' + formatPrice(priceMax) + ' FCFA';
    }
    if (prixInput) {
      prixInput.min = String(Math.floor(priceMin));
      prixInput.max = String(Math.ceil(priceMax));
      prixInput.placeholder = formatPrice(priceMin) + ' — ' + formatPrice(priceMax);
    }

    openModal();
  }

  function hideForm() {
    cards.forEach(function (c) {
      c.classList.remove('is-selected');
    });
    if (hiddenId) {
      hiddenId.value = '';
    }
    if (hiddenType) {
      hiddenType.value = '';
    }
    closeModal();
  }

  cards.forEach(function (card) {
    card.addEventListener('click', function () {
      showForm(card);
    });
  });

  if (searchInput) {
    searchInput.addEventListener('input', applyFilters);
  }
  if (sortSelect) {
    sortSelect.addEventListener('change', function () {
      sortCards();
      applyFilters();
    });
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', hideForm);
  }
  if (modalBackdrop) {
    modalBackdrop.addEventListener('click', hideForm);
  }

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modalOverlay && modalOverlay.classList.contains('is-visible')) {
      hideForm();
    }
  });

  if (modalOverlay && modalOverlay.classList.contains('is-visible')) {
    lockBodyScroll(true);
  }

  applyFilters();
}

function initCpVoiceNote() {
  var fileInput = document.getElementById('note_vocale');
  var idlePanel = document.getElementById('cp-voice-idle');
  var recordingPanel = document.getElementById('cp-voice-recording');
  var previewPanel = document.getElementById('cp-voice-preview');
  var recordBtn = document.getElementById('cp-voice-record-btn');
  var stopBtn = document.getElementById('cp-voice-stop');
  var playBtn = document.getElementById('cp-voice-play');
  var deleteBtn = document.getElementById('cp-voice-delete');
  var timerEl = document.getElementById('cp-voice-timer');
  var durationEl = document.getElementById('cp-voice-duration');
  var errorEl = document.getElementById('cp-voice-error');

  if (!fileInput || !idlePanel || !recordingPanel || !previewPanel || !recordBtn) {
    return;
  }

  var mediaRecorder = null;
  var mediaStream = null;
  var audioChunks = [];
  var audioBlob = null;
  var audioUrl = '';
  var audioPreview = new Audio();
  var timerInterval = null;
  var startedAt = 0;
  var maxSeconds = 120;

  function formatTime(totalSeconds) {
    var m = Math.floor(totalSeconds / 60);
    var s = totalSeconds % 60;
    return m + ':' + String(s).padStart(2, '0');
  }

  function showError(message) {
    if (!errorEl) {
      return;
    }
    if (!message) {
      errorEl.hidden = true;
      errorEl.textContent = '';
      return;
    }
    errorEl.hidden = false;
    errorEl.textContent = message;
  }

  function showIdle() {
    idlePanel.hidden = false;
    recordingPanel.hidden = true;
    previewPanel.hidden = true;
  }

  function showRecording() {
    idlePanel.hidden = true;
    recordingPanel.hidden = false;
    previewPanel.hidden = true;
  }

  function showPreview() {
    idlePanel.hidden = true;
    recordingPanel.hidden = true;
    previewPanel.hidden = false;
  }

  function stopTracks() {
    if (mediaStream) {
      mediaStream.getTracks().forEach(function (track) {
        track.stop();
      });
      mediaStream = null;
    }
  }

  function clearTimer() {
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }
  }

  function revokeAudioUrl() {
    if (audioUrl) {
      URL.revokeObjectURL(audioUrl);
      audioUrl = '';
    }
  }

  function syncFileInput() {
    if (!audioBlob) {
      fileInput.value = '';
      return;
    }
    var ext = 'webm';
    if (audioBlob.type.indexOf('ogg') !== -1) {
      ext = 'ogg';
    } else if (audioBlob.type.indexOf('mp4') !== -1 || audioBlob.type.indexOf('m4a') !== -1) {
      ext = 'm4a';
    }
    var file = new File([audioBlob], 'note-vocale.' + ext, { type: audioBlob.type || 'audio/webm' });
    var dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    fileInput.files = dataTransfer.files;
  }

  function resetRecording() {
    clearTimer();
    stopTracks();
    mediaRecorder = null;
    audioChunks = [];
    audioBlob = null;
    revokeAudioUrl();
    audioPreview.pause();
    audioPreview.removeAttribute('src');
    if (playBtn) {
      playBtn.innerHTML = '<i class="fas fa-play" aria-hidden="true"></i>';
    }
    fileInput.value = '';
    showIdle();
    showError('');
  }

  function finishRecording() {
    clearTimer();
    stopTracks();
    if (!mediaRecorder || !audioChunks.length) {
      resetRecording();
      return;
    }

    audioBlob = new Blob(audioChunks, { type: mediaRecorder.mimeType || 'audio/webm' });
    revokeAudioUrl();
    audioUrl = URL.createObjectURL(audioBlob);
    audioPreview.src = audioUrl;

    var elapsed = Math.max(1, Math.round((Date.now() - startedAt) / 1000));
    if (durationEl) {
      durationEl.textContent = formatTime(elapsed);
    }
    syncFileInput();
    showPreview();
    mediaRecorder = null;
    audioChunks = [];
  }

  function startRecording() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      showError('Votre navigateur ne permet pas l\'enregistrement vocal.');
      return;
    }

    showError('');
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
      mediaStream = stream;
      audioChunks = [];
      var mimeType = '';
      if (typeof MediaRecorder !== 'undefined') {
        if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
          mimeType = 'audio/webm;codecs=opus';
        } else if (MediaRecorder.isTypeSupported('audio/webm')) {
          mimeType = 'audio/webm';
        } else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
          mimeType = 'audio/ogg;codecs=opus';
        }
      }

      try {
        mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType: mimeType }) : new MediaRecorder(stream);
      } catch (e) {
        stopTracks();
        showError('Impossible de démarrer l\'enregistrement sur cet appareil.');
        return;
      }

      mediaRecorder.addEventListener('dataavailable', function (event) {
        if (event.data && event.data.size > 0) {
          audioChunks.push(event.data);
        }
      });

      mediaRecorder.addEventListener('stop', function () {
        finishRecording();
      });

      startedAt = Date.now();
      mediaRecorder.start();
      showRecording();
      if (timerEl) {
        timerEl.textContent = '0:00';
      }

      clearTimer();
      timerInterval = setInterval(function () {
        var elapsed = Math.floor((Date.now() - startedAt) / 1000);
        if (timerEl) {
          timerEl.textContent = formatTime(elapsed);
        }
        if (elapsed >= maxSeconds && mediaRecorder && mediaRecorder.state === 'recording') {
          mediaRecorder.stop();
        }
      }, 250);
    }).catch(function () {
      showError('Autorisez l\'accès au micro pour enregistrer votre message.');
    });
  }

  recordBtn.addEventListener('click', startRecording);

  if (stopBtn) {
    stopBtn.addEventListener('click', function () {
      if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
      }
    });
  }

  if (playBtn) {
    playBtn.addEventListener('click', function () {
      if (!audioUrl) {
        return;
      }
      if (audioPreview.paused) {
        audioPreview.play();
        playBtn.innerHTML = '<i class="fas fa-pause" aria-hidden="true"></i>';
      } else {
        audioPreview.pause();
        playBtn.innerHTML = '<i class="fas fa-play" aria-hidden="true"></i>';
      }
    });
  }

  audioPreview.addEventListener('ended', function () {
    if (playBtn) {
      playBtn.innerHTML = '<i class="fas fa-play" aria-hidden="true"></i>';
    }
  });

  if (deleteBtn) {
    deleteBtn.addEventListener('click', resetRecording);
  }
}
