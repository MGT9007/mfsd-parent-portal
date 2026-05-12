/**
 * MFSD Parent Portal JavaScript
 * 
 * Handles accordion functionality and UI interactions
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        initWeekAccordions();
        initCourseTabs();
    });

    /**
     * Course landing page tabs (My Courses / New Courses / All Courses)
     */
    function initCourseTabs() {
        $(document).on('click', '.mfsd-pp__tab', function() {
            const $tab      = $(this);
            const target    = $tab.data('tab');
            const $landing  = $tab.closest('.mfsd-pp--landing');

            // Update tab button states
            $landing.find('.mfsd-pp__tab').each(function() {
                const active = $(this).data('tab') === target;
                $(this)
                    .attr('aria-selected', active)
                    .css({
                        'color':              active ? '#4F46E5' : '#6B7280',
                        'border-bottom-color': active ? '#4F46E5' : 'transparent'
                    });
            });

            // Show/hide panels
            $landing.find('.mfsd-pp__tab-panel').each(function() {
                if ($(this).data('panel') === target) {
                    $(this).css('display', '');
                } else {
                    $(this).css('display', 'none');
                }
            });
        });
    }

    /**
     * Initialize week accordions
     */
    function initWeekAccordions() {
        $('.mfsd-pp__week-header').on('click', function(e) {
            e.preventDefault();
            
            const $header = $(this);
            const $content = $header.siblings('.mfsd-pp__week-content');
            const isExpanded = $header.attr('aria-expanded') === 'true';
            
            // Toggle this section
            $header.attr('aria-expanded', !isExpanded);
            
            if (isExpanded) {
                $content.attr('hidden', true);
            } else {
                $content.removeAttr('hidden');
            }
            
            // Optionally close other sections (comment out for independent accordions)
            // closeOtherWeeks($header);
        });
    }

    /**
     * Close other week sections
     */
    function closeOtherWeeks($currentHeader) {
        const $parent = $currentHeader.closest('.mfsd-pp__student');
        
        $parent.find('.mfsd-pp__week-header').not($currentHeader).each(function() {
            const $header = $(this);
            const $content = $header.siblings('.mfsd-pp__week-content');
            
            $header.attr('aria-expanded', 'false');
            $content.attr('hidden', true);
        });
    }

    /**
     * Animate progress rings on scroll into view
     */
    function initProgressRingAnimation() {
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('mfsd-pp__progress-ring--animate');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            document.querySelectorAll('.mfsd-pp__progress-ring').forEach(ring => {
                observer.observe(ring);
            });
        }
    }

})(jQuery);
