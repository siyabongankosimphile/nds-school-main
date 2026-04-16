/**
 * NDS SweetAlert2 Global Notification System
 * Provides easy-to-use notification functions throughout the plugin
 */

(function() {
    'use strict';

    // Ensure SweetAlert2 is loaded
    if (typeof Swal === 'undefined') {
        console.error('SweetAlert2 is not loaded. Please enqueue it first.');
        return;
    }

    /**
     * Global NDS Notification Object
     */
    window.NDSNotification = {
        /**
         * Show success notification
         * @param {string} message - Success message
         * @param {string} title - Optional title (default: "Success!")
         */
        success: function(message, title) {
            Swal.fire({
                icon: 'success',
                title: title || 'Success!',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
        },

        /**
         * Show error notification
         * @param {string} message - Error message
         * @param {string} title - Optional title (default: "Error!")
         */
        error: function(message, title) {
            Swal.fire({
                icon: 'error',
                title: title || 'Error!',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 5000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
        },

        /**
         * Show warning notification
         * @param {string} message - Warning message
         * @param {string} title - Optional title (default: "Warning!")
         */
        warning: function(message, title) {
            Swal.fire({
                icon: 'warning',
                title: title || 'Warning!',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
        },

        /**
         * Show info notification
         * @param {string} message - Info message
         * @param {string} title - Optional title (default: "Info")
         */
        info: function(message, title) {
            Swal.fire({
                icon: 'info',
                title: title || 'Info',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });
        },

        /**
         * Show confirmation dialog
         * @param {string} message - Confirmation message
         * @param {string} title - Optional title (default: "Are you sure?")
         * @param {function} onConfirm - Callback function when confirmed
         * @param {function} onCancel - Optional callback function when cancelled
         */
        confirm: function(message, title, onConfirm, onCancel) {
            Swal.fire({
                title: title || 'Are you sure?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, proceed!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed && typeof onConfirm === 'function') {
                    onConfirm();
                } else if (result.isDismissed && typeof onCancel === 'function') {
                    onCancel();
                }
            });
        },

        /**
         * Show loading state
         * @param {string} message - Loading message
         * @param {string} title - Optional title (default: "Loading...")
         */
        loading: function(message, title) {
            Swal.fire({
                title: title || 'Loading...',
                text: message || 'Please wait',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        },

        /**
         * Close current SweetAlert
         */
        close: function() {
            Swal.close();
        },

        /**
         * Show custom alert with more options
         * @param {object} options - SweetAlert2 options
         */
        custom: function(options) {
            Swal.fire(options);
        }
    };

    // Alias for shorter usage
    window.NDSNotify = window.NDSNotification;

})();



