document.addEventListener('DOMContentLoaded', function() {
  try {
    const params = new URLSearchParams(window.location.search);
    const success = params.get('success');
    const error = params.get('error');

    if (typeof Toastify !== 'undefined') {
      if (success) {
        Toastify({ text: success.replace(/_/g, ' '), backgroundColor: '#16a34a', duration: 3500 }).showToast();
      }
      if (error) {
        Toastify({ text: error.replace(/_/g, ' '), backgroundColor: '#dc2626', duration: 4000 }).showToast();
      }
    }
  } catch (e) { /* noop */ }
});




