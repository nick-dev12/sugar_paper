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

  if (!fileInput || !uploadBox || !uploadTrigger || !previewGrid) {
    return;
  }

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
});
