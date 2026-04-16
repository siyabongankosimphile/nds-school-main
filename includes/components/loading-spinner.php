<?php
/**
 * Loading Spinner Component
 * Reusable loading component for export/import operations
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="nds-loading-overlay" class="nds-loading-overlay" style="display: none;">
    <div class="nds-loading-container">
        <div class="nds-loading-spinner"></div>
        <p class="nds-loading-text">Processing...</p>
        <p class="nds-loading-details" id="nds-loading-details"></p>
    </div>
</div>

<style>
.nds-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999999;
}

.nds-loading-container {
    background: #fff;
    padding: 30px 40px;
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    text-align: center;
    max-width: 400px;
    width: 90%;
}

.nds-loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #2271b1;
    border-radius: 50%;
    animation: nds-spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes nds-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.nds-loading-text {
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
    margin: 0 0 10px 0;
}

.nds-loading-details {
    font-size: 13px;
    color: #646970;
    margin: 0;
    font-style: italic;
}
</style>

<script>
(function($) {
    'use strict';
    
    window.ndsLoading = {
        show: function(message = 'Processing...', details = '') {
            $('#nds-loading-text').text(message);
            if (details) {
                $('#nds-loading-details').text(details).show();
            } else {
                $('#nds-loading-details').hide();
            }
            $('#nds-loading-overlay').fadeIn(200);
        },
        
        hide: function() {
            $('#nds-loading-overlay').fadeOut(200);
        },
        
        update: function(message, details = '') {
            if (message) $('#nds-loading-text').text(message);
            if (details) {
                $('#nds-loading-details').text(details).show();
            }
        }
    };
    
    // Show loading spinner on form submissions
    $(document).on('submit', 'form[data-nds-loading="true"]', function(e) {
        var form = $(this);
        var button = form.find('button[type="submit"]');
        var buttonText = button.data('loading-text') || 'Processing...';
        var actionDetails = form.data('loading-details') || '';
        
        // Store original button state
        button.data('original-html', button.html());
        button.prop('disabled', true).html(
            '<span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>' + buttonText
        );
        
        window.ndsLoading.show(buttonText, actionDetails);
    });
    
    // Handle export/import buttons
    $(document).on('click', '.nds-export-btn, .nds-import-btn', function(e) {
        var button = $(this);
        var form = button.closest('form');
        
        if (form.length === 0) {
            // If button isn't in a form, create one
            var url = button.data('action-url') || button.attr('href');
            if (url && !button.hasClass('disabled')) {
                var buttonText = button.data('loading-text') || button.text();
                var details = button.data('loading-details') || '';
                
                button.prop('disabled', true).addClass('disabled');
                var originalHtml = button.html();
                button.html(
                    '<span class="spinner is-active" style="float: none; margin: 0 8px 0 0;"></span>' + buttonText
                );
                
                window.ndsLoading.show(buttonText, details);
                
                // Store for restoration if needed
                button.data('original-html', originalHtml);
            }
        }
    });
    
    // Hide loading on page load/unload
    $(window).on('beforeunload', function() {
        window.ndsLoading.hide();
    });
    
})(jQuery);
</script>