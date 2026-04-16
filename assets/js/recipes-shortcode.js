/**
 * NDS Recipes Shortcode JavaScript
 * Handles interactive features for recipe cards
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        initRecipeCards();
    });
    
    /**
     * Initialize recipe cards interactions
     */
    function initRecipeCards() {
        // Lazy loading for images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            $('.nds-recipe-image img[data-src]').each(function() {
                imageObserver.observe(this);
            });
        }
        
        // Add loading animation
        $('.nds-recipe-card').on('mouseenter', function() {
            $(this).addClass('loading');
        }).on('mouseleave', function() {
            $(this).removeClass('loading');
        });
        
        // Click tracking for analytics (if needed)
        $('.nds-recipe-title a').on('click', function() {
            const recipeName = $(this).text();
            // You can add analytics tracking here
            console.log('Recipe clicked:', recipeName);
        });
    }
    
    /**
     * Utility function to debounce events
     */
    function debounce(func, wait, immediate) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }
    
    /**
     * Add CSS for disabled state
     */
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .nds-carousel-arrow.disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }
            .nds-recipe-card.loading {
                animation: pulse 1s infinite;
            }
            @keyframes pulse {
                0% { opacity: 1; }
                50% { opacity: 0.8; }
                100% { opacity: 1; }
            }
        `)
        .appendTo('head');

})(jQuery);
