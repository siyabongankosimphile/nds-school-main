/**
 * NDS Hero Carousel (simple)
 * - Autoplay every 10s
 * - Smooth slide transition (700ms)
 * - Staggered text for elements with .nds-stagger and optional data-delay
 */
(function($){
    'use strict';

    function initCarousels(){
        $('.nds-hero-carousel').each(function(){
            var $root = $(this);
            var id = $root.attr('id');
            if(!id) return;
            // Guard: prevent multiple inits on the same element
            if ($root.data('swiper-initialized')) {
                return;
            }

            var loop = ($root.data('loop')+'' === 'true');
            var autoplayEnabled = ($root.data('autoplay')+'' === 'true');
            var autoplayDelay = parseInt($root.data('autoplay-delay'), 10) || 10000;
            var effect = ($root.data('effect') || 'fade');

            var swiperOptions = {
                loop: loop,
                effect: effect,
                speed: 900,
                navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
                pagination: { el: '.swiper-pagination', clickable: true }
            };
            if(effect === 'fade'){
                swiperOptions.fadeEffect = { crossFade: true };
            }
            if (autoplayEnabled) {
                swiperOptions.autoplay = {
                    delay: autoplayDelay,
                    disableOnInteraction: false,
                    pauseOnMouseEnter: true
                };
            }

            var swiper = new Swiper('#'+id+' .swiper', swiperOptions);

            // Sync Ken Burns zoom duration with autoplay delay if enabled
            var zoomMs = autoplayEnabled ? autoplayDelay : 10000;
            $root.css('--hero-zoom-duration', (zoomMs/1000)+'s');

            // Staggered text handling
            function runStagger($slide){
                if(!$slide || $slide.length === 0) return;
                var $items = $slide.find('.nds-stagger');
                // reset first
                $items.removeClass('is-in');
                $items.each(function(idx){
                    var $el = $(this);
                    var delay = parseInt($el.data('delay'), 10);
                    if(isNaN(delay)) delay = 120 + (idx * 120);
                    setTimeout(function(){
                        $el.addClass('is-in');
                    }, delay);
                });
            }

            // initial stagger for the first slide
            var $initial = $root.find('.swiper-slide-active');
            runStagger($initial);

            swiper.on('slideChangeTransitionStart', function(){
                // remove classes from all slides to prepare new animation
                $root.find('.nds-stagger').removeClass('is-in');
            });
            swiper.on('slideChangeTransitionEnd', function(){
                var $active = $root.find('.swiper-slide-active');
                runStagger($active);
            });

            // Store instance (optional external access)
            $root.data('swiper', swiper);
            $root.data('swiper-initialized', true);
        });
    }

    $(function(){
        if(typeof Swiper === 'undefined') return;
        initCarousels();
    });
})(jQuery);


