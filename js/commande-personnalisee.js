/**
 * @deprecated Utiliser /js/cp-form-modal.js
 */
document.addEventListener('DOMContentLoaded', function () {
  if (typeof window.CpFormModal === 'undefined') {
    var s = document.createElement('script');
    s.src = '/js/cp-form-modal.js';
    document.body.appendChild(s);
  }
});
